<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\BillingCycle;
use App\Models\Client;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\PaymentRecord;
use App\Services\InvoicePdfService;
use App\Services\InvoiceGenerationService;
use App\Services\InvoiceStatusService;
use App\Services\InvoiceSendingService;
use App\Services\XenditService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function activeNext()
    {
        $nextCycle = app(\App\Services\BillingCycleService::class)->getOrCreateNextCycle();
        $q = request('q');

        $clients = \App\Models\Client::query()
            ->where('is_active', true)
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', '%'.$q.'%')->orWhere('code', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('invoices.active_next', compact('clients', 'nextCycle', 'q'));
    }

    public function index()
    {
        $query = Invoice::query()->with(['client', 'billingCycle', 'paymentRecord']);

        if ($clientId = request('client_id')) {
            $query->where('client_id', $clientId);
        }
        if ($status = request('status')) {
            $query->where('status', $status);
        }
        if ($cycle = request('cycle')) {
            $parts = explode('-', $cycle);
            if (count($parts) === 2) {
                $query->whereHas('billingCycle', function ($q) use ($parts) {
                    $q->where('year', (int) $parts[0])->where('month', (int) $parts[1]);
                });
            }
        }
        if ($from = request('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = request('to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        // Hide void by default unless the user explicitly includes them.
        if (! request()->boolean('show_void')) {
            $query->where('status', '!=', Invoice::STATUS_VOID);
        }

        $sortDir = request('sort_dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy('issue_date', $sortDir)->orderBy('id', $sortDir);

        $invoices = $query->paginate(25)->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'clients' => \App\Models\Client::query()->orderBy('name')->get(),
            'cycles' => BillingCycle::query()->orderByDesc('year')->orderByDesc('month')->get(),
        ]);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['client', 'billingCycle', 'details', 'paymentRecord', 'emailLogs', 'statusHistories.performer']);

        $defaultRecipientEmail = trim((string) AppSetting::getValue('default_recipient_email', ''));

        return view('invoices.show', compact('invoice', 'defaultRecipientEmail'));
    }

    public function createAdhoc()
    {
        $clients = Client::query()->where('is_active', true)->orderBy('name')->get();

        return view('invoices.create_adhoc', [
            'clients' => $clients,
            'today' => now('Asia/Jakarta')->toDateString(),
        ]);
    }

    public function storeAdhoc(
        Request $request,
        InvoiceStatusService $statusService,
        InvoicePdfService $pdfService,
        XenditService $xenditService,
        InvoiceSendingService $sendingService
    ) {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:1',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'add_stamp_duty' => 'nullable|boolean',
            'save_as_draft' => 'nullable|boolean',
        ]);

        $client = Client::query()->findOrFail((int) $validated['client_id']);
        $addStampDuty = (bool) $request->boolean('add_stamp_duty');
        $saveAsDraft = (bool) $request->boolean('save_as_draft');

        $invoice = DB::transaction(function () use ($validated, $client, $addStampDuty, $saveAsDraft, $statusService, $pdfService, $xenditService) {
            $baseAmount = (float) $validated['amount'];
            $stampDutyAmount = $addStampDuty ? 10000.0 : 0.0;
            $subtotal = $baseAmount + $stampDutyAmount;
            $tax = 0;
            $total = $subtotal + $tax;
            $stampingRequired = $subtotal >= 5000000 || $addStampDuty;
            $defaultRecipientEmail = trim((string) AppSetting::getValue('default_recipient_email', ''));

            $invoice = Invoice::query()->create([
                'client_id' => $client->id,
                'billing_cycle_id' => null,
                'invoice_number' => $this->buildAdhocInvoiceNumber(),
                'invoice_type' => Invoice::TYPE_ADHOC,
                'status' => Invoice::STATUS_GENERATING,
                'currency' => $client->currency ?: 'IDR',
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'stamping_required' => $stampingRequired,
                'stamping_status' => $stampingRequired ? 'pending' : 'not_required',
                'generated_at' => now('Asia/Jakarta'),
                'email_sent' => false,
                'recipient_email' => $defaultRecipientEmail !== '' ? $defaultRecipientEmail : $client->email,
                'email_send_mode_snapshot' => AppSetting::getValue('send_mode', 'manual'),
            ]);

            $statusService->transition($invoice, Invoice::STATUS_GENERATING, 'Adhoc invoice creation started', auth()->id());

            InvoiceDetail::query()->create([
                'invoice_id' => $invoice->id,
                'item_code' => 'ADHOC',
                'description' => $validated['description'],
                'quantity' => 1,
                'unit_price' => $baseAmount,
                'line_total' => $baseAmount,
                'position' => 1,
            ]);

            if ($addStampDuty) {
                InvoiceDetail::query()->create([
                    'invoice_id' => $invoice->id,
                    'item_code' => 'STAMP_DUTY',
                    'description' => 'Stamp duty',
                    'quantity' => 1,
                    'unit_price' => $stampDutyAmount,
                    'line_total' => $stampDutyAmount,
                    'position' => 2,
                ]);
            }

            $invoice->generated_pdf_path = $pdfService->generate($invoice);
            $invoice->save();

            $statusService->transition($invoice, Invoice::STATUS_GENERATED, 'Adhoc invoice PDF generated', auth()->id());

            if ($saveAsDraft) {
                // Keep adhoc invoice in generated state until user confirms it.
                return $invoice;
            }

            if ($stampingRequired) {
                $statusService->transition($invoice, Invoice::STATUS_PENDING_STAMPING, 'Waiting for manual e-meterai stamping', auth()->id());
            } else {
                $invoice->ready_to_send_at = now('Asia/Jakarta');
                $invoice->save();
                $statusService->transition($invoice, Invoice::STATUS_READY_TO_SEND, 'Adhoc invoice ready to send', auth()->id());
            }

            $this->createPaymentRecordForInvoice($invoice, $client, $xenditService);

            return $invoice;
        });

        if (! $saveAsDraft) {
            $sendingService->maybeAutoSend($invoice, auth()->id());
        }

        return redirect()->route('invoices.show', $invoice)->with('status', $saveAsDraft ? 'Adhoc invoice saved as draft' : 'Adhoc invoice created');
    }

    public function confirmAdhocDraft(Invoice $invoice, InvoiceStatusService $statusService, XenditService $xenditService, InvoiceSendingService $sendingService)
    {
        if ($invoice->invoice_type !== Invoice::TYPE_ADHOC) {
            return back()->withErrors(['invoice' => 'Only adhoc invoices can be confirmed here.']);
        }

        if ($invoice->status !== Invoice::STATUS_GENERATED) {
            return back()->withErrors(['invoice' => 'Only generated draft invoices can be confirmed.']);
        }

        if ($invoice->paymentRecord()->exists()) {
            return back()->withErrors(['invoice' => 'This invoice is already confirmed.']);
        }

        DB::transaction(function () use ($invoice, $statusService, $xenditService) {
            $invoice->refresh();

            if ($invoice->stamping_required) {
                $statusService->transition($invoice, Invoice::STATUS_PENDING_STAMPING, 'Draft confirmed, waiting for manual e-meterai stamping', auth()->id());
            } else {
                $invoice->ready_to_send_at = now('Asia/Jakarta');
                $invoice->save();
                $statusService->transition($invoice, Invoice::STATUS_READY_TO_SEND, 'Draft confirmed and ready to send', auth()->id());
            }

            $this->createPaymentRecordForInvoice($invoice, $invoice->client, $xenditService);
        });

        $sendingService->maybeAutoSend($invoice->fresh(), auth()->id());

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft invoice confirmed.');
    }

    public function voidInvoice(Request $request, Invoice $invoice, InvoiceStatusService $statusService)
    {
        $validated = $request->validate([
            'void_reason' => 'nullable|string|max:500',
        ]);

        if ($invoice->status === Invoice::STATUS_VOID) {
            return back()->withErrors(['invoice' => 'Invoice is already void.']);
        }

        DB::transaction(function () use ($invoice, $validated, $statusService) {
            $invoice->refresh();

            if ($invoice->status === Invoice::STATUS_VOID) {
                return;
            }

            $reason = trim((string) ($validated['void_reason'] ?? ''));
            $note = $reason !== '' ? 'Voided: '.$reason : 'Voided manually';

            $statusService->transition($invoice, Invoice::STATUS_VOID, $note, auth()->id());

            if ($invoice->paymentRecord) {
                $invoice->paymentRecord->status = 'void';
                $invoice->paymentRecord->save();
            }
        });

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice marked as void.');
    }

    public function markAsSent(Request $request, Invoice $invoice, InvoiceStatusService $statusService)
    {
        $validated = $request->validate([
            'manual_sent_at' => 'nullable|date',
            'manual_note' => 'nullable|string|max:500',
        ]);

        if ($invoice->status === Invoice::STATUS_VOID) {
            return back()->withErrors(['invoice' => 'Void invoice cannot be marked as sent.']);
        }

        DB::transaction(function () use ($invoice, $validated, $statusService) {
            $invoice->refresh();

            if ($invoice->status === Invoice::STATUS_VOID) {
                return;
            }

            $sentAt = ! empty($validated['manual_sent_at'])
                ? \Illuminate\Support\Carbon::parse($validated['manual_sent_at'], 'Asia/Jakarta')
                : now('Asia/Jakarta');

            $invoice->email_sent = true;
            $invoice->sent_at = $sentAt;
            $invoice->last_error = null;
            $invoice->save();

            EmailLog::query()->create([
                'invoice_id' => $invoice->id,
                'status' => 'sent',
                'recipient' => $this->resolveRecipientEmail($invoice),
                'subject' => 'Invoice '.$invoice->invoice_number,
                'attachment_types' => [],
                'attempted_at' => $sentAt,
                'sent_at' => $sentAt,
                'error_message' => null,
            ]);

            $note = trim((string) ($validated['manual_note'] ?? ''));
            $historyNote = $note !== '' ? 'Marked as sent manually: '.$note : 'Marked as sent manually';

            if ($invoice->status !== Invoice::STATUS_PAID) {
                $statusService->transition($invoice, Invoice::STATUS_SENT, $historyNote, auth()->id());
            }
        });

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice marked as already sent.');
    }

    public function generateNextCycle(InvoiceGenerationService $service)
    {
        list($cycle, $count) = $service->generateNextCycle(auth()->id());

        return redirect()->route('invoices.index')->with('status', sprintf('Cycle %04d-%02d generated, created %d invoices', $cycle->year, $cycle->month, $count));
    }

    public function sendAllReady(InvoiceSendingService $service)
    {
        $count = $service->sendReadyInvoices(auth()->id());

        return redirect()->route('invoices.index')->with('status', 'Sent ready invoices: '.$count);
    }

    public function updateStatus(Request $request, Invoice $invoice, InvoiceStatusService $statusService)
    {
        $validated = $request->validate([
            'status_target' => 'required|in:void,sent,paid',
            'status_note' => 'nullable|string|max:500',
            'status_datetime' => 'nullable|date',
            'payment_method' => 'nullable|string|max:100|required_if:status_target,paid',
        ]);

        if ($invoice->status === Invoice::STATUS_VOID && $validated['status_target'] !== Invoice::STATUS_VOID) {
            return back()->withErrors(['invoice' => 'Void invoice cannot be updated to another status.']);
        }

        DB::transaction(function () use ($invoice, $validated, $statusService) {
            $invoice->refresh();
            $this->applyManualStatusUpdate($invoice, $validated, $statusService, false);
        });

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice status updated.');
    }

    public function bulkUpdateStatus(Request $request, InvoiceStatusService $statusService)
    {
        $validated = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'integer|exists:invoices,id',
            'status_target' => 'required|in:void,sent,paid',
            'status_note' => 'nullable|string|max:500',
            'status_datetime' => 'nullable|date',
            'payment_method' => 'nullable|string|max:100|required_if:status_target,paid',
        ]);

        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($validated, $statusService, &$updated, &$skipped) {
            $invoices = Invoice::query()
                ->whereIn('id', $validated['invoice_ids'])
                ->with(['client', 'paymentRecord'])
                ->get();

            foreach ($invoices as $invoice) {
                if ($invoice->status === Invoice::STATUS_VOID && $validated['status_target'] !== Invoice::STATUS_VOID) {
                    $skipped++;
                    continue;
                }

                $this->applyManualStatusUpdate($invoice, $validated, $statusService, true);
                $updated++;
            }
        });

        return redirect()->route('invoices.index')->with('status', sprintf('Bulk update completed: %d updated, %d skipped.', $updated, $skipped));
    }

    public function updateRecipient(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'recipient_email' => 'required|email',
        ]);

        $invoice->recipient_email = $validated['recipient_email'];
        $invoice->save();

        return redirect()->route('invoices.show', $invoice)->with('status', 'Recipient updated.');
    }

    public function downloadPdf(Invoice $invoice, InvoicePdfService $pdfService)
    {
        if (! $invoice->generated_pdf_path || ! Storage::disk('local')->exists($invoice->generated_pdf_path)) {
            $invoice->generated_pdf_path = $pdfService->generate($invoice);
            $invoice->save();
        }

        return response()->download(
            storage_path('app/'.$invoice->generated_pdf_path),
            $invoice->invoice_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    protected function buildAdhocInvoiceNumber(): string
    {
        $year = now('Asia/Jakarta')->format('Y');
        $prefix = "INV/{$year}/";

        $maxSeq = 0;
        $numbers = Invoice::query()->where('invoice_number', 'like', $prefix.'%')->pluck('invoice_number');

        foreach ($numbers as $number) {
            if (preg_match('/^INV\/\d{4}\/(\d{5})$/', $number, $matches)) {
                $maxSeq = max($maxSeq, (int) $matches[1]);
            }
        }

        $nextSeq = $maxSeq + 1;
        do {
            $candidate = $prefix.str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);
            $nextSeq++;
        } while (Invoice::query()->where('invoice_number', $candidate)->exists());

        return $candidate;
    }

    protected function createPaymentRecordForInvoice(Invoice $invoice, Client $client, XenditService $xenditService): void
    {
        $xendit = $xenditService->createPaymentLink([
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
            'provider_reference' => is_array($xendit) ? ($xendit['id'] ?? null) : null,
            'external_id' => $invoice->invoice_number,
            'payment_url' => is_array($xendit) ? ($xendit['invoice_url'] ?? null) : null,
            'status' => is_array($xendit) ? strtolower($xendit['status'] ?? 'pending') : 'pending',
            'amount' => $invoice->total_amount,
            'payload' => is_array($xendit) ? $xendit : null,
        ]);
    }

    protected function applyManualStatusUpdate(Invoice $invoice, array $validated, InvoiceStatusService $statusService, bool $isBulk): void
    {
        $targetStatus = $validated['status_target'];
        $rawNote = trim((string) ($validated['status_note'] ?? ''));
        $statusAt = ! empty($validated['status_datetime'])
            ? Carbon::parse($validated['status_datetime'], 'Asia/Jakarta')
            : now('Asia/Jakarta');

        if ($targetStatus === Invoice::STATUS_VOID) {
            $historyNote = $rawNote !== '' ? 'Voided: '.$rawNote : ($isBulk ? 'Voided via bulk operation' : 'Voided manually');
            $statusService->transition($invoice, Invoice::STATUS_VOID, $historyNote, auth()->id());

            if ($invoice->paymentRecord) {
                $invoice->paymentRecord->status = 'void';
                $invoice->paymentRecord->save();
            }

            return;
        }

        if ($targetStatus === Invoice::STATUS_SENT) {
            $invoice->email_sent = true;
            $invoice->sent_at = $statusAt;
            $invoice->last_error = null;
            $invoice->save();

            EmailLog::query()->create([
                'invoice_id' => $invoice->id,
                'status' => 'sent',
                'recipient' => $this->resolveRecipientEmail($invoice),
                'subject' => 'Invoice '.$invoice->invoice_number,
                'attachment_types' => [],
                'attempted_at' => $statusAt,
                'sent_at' => $statusAt,
                'error_message' => null,
            ]);

            $historyNote = $rawNote !== '' ? 'Marked as sent manually: '.$rawNote : 'Marked as sent manually';
            $statusService->transition($invoice, Invoice::STATUS_SENT, $historyNote, auth()->id());

            if ($invoice->paymentRecord && $invoice->paymentRecord->status !== 'paid') {
                $invoice->paymentRecord->status = 'sent';
                $invoice->paymentRecord->save();
            }

            return;
        }

        $invoice->paid_at = $statusAt;
        $invoice->save();

        $paymentMethod = trim((string) ($validated['payment_method'] ?? ''));
        $historyNote = 'Marked as paid manually';
        if ($paymentMethod !== '') {
            $historyNote .= ' via '.$paymentMethod;
        }
        if ($rawNote !== '') {
            $historyNote .= ': '.$rawNote;
        }

        $statusService->transition($invoice, Invoice::STATUS_PAID, $historyNote, auth()->id());

        if ($invoice->paymentRecord) {
            $payload = is_array($invoice->paymentRecord->payload) ? $invoice->paymentRecord->payload : [];
            if ($paymentMethod !== '') {
                $payload['manual_payment_method'] = $paymentMethod;
            }

            $invoice->paymentRecord->status = 'paid';
            $invoice->paymentRecord->paid_at = $statusAt;
            $invoice->paymentRecord->payload = $payload;
            $invoice->paymentRecord->save();
        }
    }

    protected function resolveRecipientEmail(Invoice $invoice): string
    {
        $recipient = trim((string) ($invoice->recipient_email ?? ''));
        if ($recipient !== '') {
            return $recipient;
        }

        $defaultRecipient = trim((string) AppSetting::getValue('default_recipient_email', ''));
        if ($defaultRecipient !== '') {
            return $defaultRecipient;
        }

        return (string) $invoice->client->email;
    }
}
