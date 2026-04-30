<?php

namespace App\Services;

use App\Models\Client;
use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\PaymentRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceGenerationService
{
    protected $cycleService;
    protected $statusService;
    protected $pdfService;
    protected $xenditService;

    public function __construct(
        BillingCycleService $cycleService,
        InvoiceStatusService $statusService,
        InvoicePdfService $pdfService,
        XenditService $xenditService
    ) {
        $this->cycleService = $cycleService;
        $this->statusService = $statusService;
        $this->pdfService = $pdfService;
        $this->xenditService = $xenditService;
    }

    public function generateNextCycle($performedBy = null)
    {
        $cycle = $this->cycleService->getOrCreateNextCycle();
        $created = 0;

        Client::query()->where('is_active', true)->chunkById(100, function ($clients) use ($cycle, &$created, $performedBy) {
            foreach ($clients as $client) {
                DB::transaction(function () use ($client, $cycle, &$created, $performedBy) {
                    $defaultRecipientEmail = trim((string) AppSetting::getValue('default_recipient_email', ''));

                    $invoice = Invoice::query()->firstOrCreate(
                        [
                            'client_id'        => $client->id,
                            'billing_cycle_id' => $cycle->id,
                            'invoice_type'     => Invoice::TYPE_MONTHLY,
                        ],
                        [
                            'invoice_number' => $this->buildInvoiceNumber($client->code, $cycle->year, $cycle->month),
                            'status'         => Invoice::STATUS_GENERATING,
                            'currency'       => $client->currency,
                            'issue_date'     => now('Asia/Jakarta')->toDateString(),
                            'due_date'       => now('Asia/Jakarta')->addDays((int) $client->default_due_days)->toDateString(),
                            'recipient_email' => $defaultRecipientEmail !== '' ? $defaultRecipientEmail : $client->email,
                        ]
                    );

                    if (! $invoice->wasRecentlyCreated) {
                        return;
                    }

                    $created++;
                    $this->statusService->transition($invoice, Invoice::STATUS_GENERATING, 'Invoice generation started', $performedBy);

                    $line = InvoiceDetail::query()->create([
                        'invoice_id' => $invoice->id,
                        'item_code' => $client->plan_name ?: 'BASE',
                        'description' => sprintf('Monthly billing for %s cycle %04d-%02d', $client->name, $cycle->year, $cycle->month),
                        'quantity' => 1,
                        'unit_price' => 1000000,
                        'line_total' => 1000000,
                        'position' => 1,
                    ]);

                    $subtotal = (float) $line->line_total;
                    $tax = $subtotal * 0.11;
                    $total = $subtotal + $tax;
                    $stampingRequired = $subtotal >= 5000000;

                    $invoice->fill([
                        'subtotal' => $subtotal,
                        'tax_amount' => $tax,
                        'total_amount' => $total,
                        'stamping_required' => $stampingRequired,
                        'stamping_status' => $stampingRequired ? 'pending' : 'not_required',
                        'generated_at' => now('Asia/Jakarta'),
                    ]);

                    $invoice->generated_pdf_path = $this->pdfService->generate($invoice);
                    $invoice->save();

                    $this->statusService->transition($invoice, Invoice::STATUS_GENERATED, 'PDF generated', $performedBy);

                    if ($stampingRequired) {
                        $this->statusService->transition($invoice, Invoice::STATUS_PENDING_STAMPING, 'Waiting for manual e-meterai stamping', $performedBy);
                    } else {
                        $invoice->ready_to_send_at = now('Asia/Jakarta');
                        $invoice->save();
                        $this->statusService->transition($invoice, Invoice::STATUS_READY_TO_SEND, 'Invoice ready to send', $performedBy);
                    }

                    $xendit = $this->xenditService->createPaymentLink([
                        'external_id' => $invoice->invoice_number,
                        'amount' => (float) $invoice->total_amount,
                        'description' => 'Invoice '.$invoice->invoice_number,
                        'invoice_duration' => 60 * 60 * 24 * 30,
                        'customer' => [
                            'given_names' => $client->name,
                            'email' => $client->email,
                        ],
                        'currency' => $invoice->currency,
                        'success_redirect_url' => config('app.url'),
                    ]);

                    PaymentRecord::query()->create([
                        'invoice_id' => $invoice->id,
                        'provider' => 'xendit',
                        'provider_reference' => $xendit['id'] ?? null,
                        'external_id' => $invoice->invoice_number,
                        'payment_url' => $xendit['invoice_url'] ?? null,
                        'status' => strtolower($xendit['status'] ?? 'pending'),
                        'amount' => $invoice->total_amount,
                        'payload' => $xendit,
                    ]);

                    $client->last_billed_at = now('Asia/Jakarta');
                    $client->save();
                });
            }
        });

        $cycle->generated_at = now('Asia/Jakarta');
        $cycle->save();

        Log::info('billing.generate_next_cycle.completed', [
            'billing_cycle_id' => $cycle->id,
            'created_count' => $created,
        ]);

        return [$cycle, $created];
    }

    protected function buildInvoiceNumber($clientCode, $year, $month)
    {
        return sprintf('INV-%s-%04d%02d', strtoupper($clientCode), $year, $month);
    }
}
