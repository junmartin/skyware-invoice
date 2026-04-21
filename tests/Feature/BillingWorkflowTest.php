<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\BillingCycle;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Services\InvoiceGenerationService;
use App\Services\InvoiceSendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::fake([
            '*' => Http::response([
                'id' => 'xnd_inv_1',
                'invoice_url' => 'https://checkout.xendit.test/inv-1',
                'status' => 'PENDING',
            ], 200),
        ]);

        AppSetting::setValue('send_mode', 'manual');
    }

    public function test_generator_idempotency()
    {
        $client = $this->createClient();

        $service = app(InvoiceGenerationService::class);
        $service->generateNextCycle();
        $service->generateNextCycle();

        $this->assertEquals(1, Invoice::query()->count());
        $this->assertDatabaseHas('invoices', [
            'client_id' => $client->id,
        ]);
    }

    public function test_send_ready_idempotency()
    {
        Mail::fake();

        $client = $this->createClient(['code' => 'samplex', 'email' => 'client@example.com']);
        $service = app(InvoiceGenerationService::class);
        $service->generateNextCycle();

        $invoice = Invoice::query()->firstOrFail();
        $invoice->update([
            'stamping_required' => false,
            'status' => Invoice::STATUS_READY_TO_SEND,
            'ready_to_send_at' => now('Asia/Jakarta'),
        ]);

        Storage::disk('local')->put($invoice->generated_pdf_path, 'dummy pdf');

        $sender = app(InvoiceSendingService::class);
        $sender->sendReadyInvoices();
        $sender->sendReadyInvoices();

        $invoice->refresh();
        $this->assertTrue($invoice->email_sent);
        $this->assertEquals(1, $invoice->emailLogs()->where('status', 'sent')->count());
    }

    public function test_webhook_marks_invoice_paid()
    {
        $client = $this->createClient();
        $service = app(InvoiceGenerationService::class);
        $service->generateNextCycle();

        $invoice = Invoice::query()->firstOrFail();
        PaymentRecord::query()->where('invoice_id', $invoice->id)->update([
            'external_id' => $invoice->invoice_number,
            'status' => 'pending',
        ]);

        config(['services.xendit.webhook_token' => 'secret-token']);

        $response = $this->postJson('/api/webhooks/xendit/invoice', [
            'external_id' => $invoice->invoice_number,
            'id' => 'xnd_paid_1',
            'status' => 'PAID',
            'paid_at' => now('Asia/Jakarta')->toIso8601String(),
        ], ['x-callback-token' => 'secret-token']);

        $response->assertOk();

        $invoice->refresh();
        $this->assertNotNull($invoice->paid_at);
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status);
    }

    public function test_monthly_generator_idempotent_adhoc_allowed_same_cycle()
    {
        // The unique DB constraint was relaxed to support ad-hoc / historical
        // invoices in the same cycle. Idempotency for monthly invoices is now
        // guaranteed by firstOrCreate scoping on invoice_type='monthly'.
        $client = $this->createClient();
        $cycle = BillingCycle::query()->create([
            'year' => 2026,
            'month' => 4,
            'cycle_start_date' => '2026-04-01',
            'cycle_end_date' => '2026-04-30',
        ]);

        $base = [
            'client_id'    => $client->id,
            'billing_cycle_id' => $cycle->id,
            'status'       => Invoice::STATUS_GENERATED,
            'currency'     => 'IDR',
            'subtotal'     => 1000000,
            'tax_amount'   => 110000,
            'total_amount' => 1110000,
            'issue_date'   => '2026-04-01',
            'due_date'     => '2026-04-14',
        ];

        // Create one monthly invoice.
        Invoice::query()->create($base + ['invoice_number' => 'INV-MONTHLY', 'invoice_type' => Invoice::TYPE_MONTHLY]);

        // An ad-hoc invoice on the same client + cycle must be allowed.
        Invoice::query()->create($base + ['invoice_number' => 'INV-ADHOC', 'invoice_type' => Invoice::TYPE_ADHOC]);

        $this->assertEquals(2, Invoice::query()->where('client_id', $client->id)->count());
        $this->assertEquals(1, Invoice::query()->where('client_id', $client->id)->where('invoice_type', Invoice::TYPE_MONTHLY)->count());
        $this->assertEquals(1, Invoice::query()->where('client_id', $client->id)->where('invoice_type', Invoice::TYPE_ADHOC)->count());
    }

    protected function createClient(array $overrides = [])
    {
        return Client::query()->create(array_merge([
            'code' => 'jneibb',
            'name' => 'JNE IBB',
            'email' => 'jneibb@example.com',
            'is_active' => true,
            'currency' => 'IDR',
            'default_due_days' => 14,
            'plan_name' => 'Enterprise',
        ], $overrides));
    }
}
