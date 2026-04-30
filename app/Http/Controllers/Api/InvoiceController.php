<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateInvoiceRequest;
use App\Http\Requests\Api\VoidInvoiceRequest;
use App\Http\Requests\Api\MarkInvoiceAsSentRequest;
use App\Http\Requests\Api\RecordPaymentRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\StatusHistoryResource;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Services\InvoicePdfService;
use App\Services\InvoiceStatusService;
use App\Services\InvoiceSendingService;
use App\Services\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List all invoices with filters and pagination
     * GET /api/invoices
     * Query params: page, per_page, status, client_id, from, to, show_void
     */
    public function index(Request $request)
    {
        $query = Invoice::query()->with(['client', 'billingCycle', 'paymentRecord']);

        // Filter by client
        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', $clientId);
        }

        // Filter by status
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Filter by date range
        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Hide void by default unless explicitly included
        if (!$request->boolean('show_void')) {
            $query->where('status', '!=', Invoice::STATUS_VOID);
        }

        // Sorting
        $sortDir = $request->query('sort_dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy('issue_date', $sortDir)->orderBy('id', $sortDir);

        $perPage = $request->query('per_page', 20);
        $invoices = $query->paginate($perPage);

        return ApiResponse::paginated($invoices, 'Invoices retrieved');
    }

    /**
     * Get single invoice details
     * GET /api/invoices/{id}
     */
    public function show(Invoice $invoice)
    {
        $invoice->load(['client', 'billingCycle', 'details', 'paymentRecord', 'emailLogs', 'statusHistories.performer']);

        return ApiResponse::success(new InvoiceResource($invoice), 'Invoice retrieved');
    }

    /**
     * Create new adhoc invoice
     * POST /api/invoices
     */
    public function store(
        CreateInvoiceRequest $request,
        InvoiceStatusService $statusService,
        InvoicePdfService $pdfService,
        XenditService $xenditService,
        InvoiceSendingService $sendingService
    ) {
        $validated = $request->validated();
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

            $statusService->transition($invoice, Invoice::STATUS_GENERATING, 'Adhoc invoice creation started via API', auth()->id());

            // Add invoice detail
            InvoiceDetail::query()->create([
                'invoice_id' => $invoice->id,
                'item_code' => 'ADHOC',
                'description' => $validated['description'],
                'quantity' => 1,
                'unit_price' => $baseAmount,
                'line_total' => $baseAmount,
                'position' => 1,
            ]);

            // Add stamp duty if needed
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

            // Generate PDF
            $invoice->generated_pdf_path = $pdfService->generate($invoice);
            $invoice->save();

            $statusService->transition($invoice, Invoice::STATUS_GENERATED, 'Adhoc invoice PDF generated', auth()->id());

            if ($saveAsDraft) {
                return $invoice;
            }

            // Update stamping status
            if ($stampingRequired) {
                $statusService->transition($invoice, Invoice::STATUS_PENDING_STAMPING, 'Waiting for manual e-meterai stamping', auth()->id());
            } else {
                $invoice->ready_to_send_at = now('Asia/Jakarta');
                $invoice->save();
                $statusService->transition($invoice, Invoice::STATUS_READY_TO_SEND, 'Adhoc invoice ready to send', auth()->id());
            }

            // Create payment record
            $this->createPaymentRecordForInvoice($invoice, $client, $xenditService);

            return $invoice;
        });

        if (!$saveAsDraft) {
            $sendingService->maybeAutoSend($invoice, auth()->id());
        }

        return ApiResponse::success(
            new InvoiceResource($invoice->fresh()),
            'Invoice created successfully',
            201
        );
    }

    /**
     * Confirm adhoc draft invoice
     * POST /api/invoices/{id}/confirm-draft
     */
    public function confirmDraft(
        Invoice $invoice,
        InvoiceStatusService $statusService,
        XenditService $xenditService,
        InvoiceSendingService $sendingService
    ) {
        if ($invoice->invoice_type !== Invoice::TYPE_ADHOC) {
            return ApiResponse::error('Only adhoc invoices can be confirmed', null, 400);
        }

        if ($invoice->status !== Invoice::STATUS_GENERATED) {
            return ApiResponse::error('Only generated draft invoices can be confirmed', null, 400);
        }

        if ($invoice->paymentRecord()->exists()) {
            return ApiResponse::error('This invoice is already confirmed', null, 400);
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

        return ApiResponse::success(
            new InvoiceResource($invoice->fresh()),
            'Draft invoice confirmed'
        );
    }

    /**
     * Send invoice via email
     * POST /api/invoices/{id}/send-email
     */
    public function sendEmail(Invoice $invoice, InvoiceSendingService $sendingService)
    {
        if ($invoice->status === Invoice::STATUS_VOID) {
            return ApiResponse::error('Cannot send void invoice', null, 400);
        }

        try {
            $sendingService->sendSingle($invoice, auth()->id());

            return ApiResponse::success(
                new InvoiceResource($invoice->fresh()),
                'Invoice sent successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to send invoice: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Void invoice
     * POST /api/invoices/{id}/void
     */
    public function void(
        VoidInvoiceRequest $request,
        Invoice $invoice,
        InvoiceStatusService $statusService
    ) {
        if ($invoice->status === Invoice::STATUS_VOID) {
            return ApiResponse::error('Invoice is already void', null, 400);
        }

        DB::transaction(function () use ($invoice, $request, $statusService) {
            $invoice->refresh();

            if ($invoice->status === Invoice::STATUS_VOID) {
                return;
            }

            $reason = trim((string) ($request->input('void_reason') ?? ''));
            $note = $reason !== '' ? 'Voided: ' . $reason : 'Voided manually';

            $statusService->transition($invoice, Invoice::STATUS_VOID, $note, auth()->id());

            if ($invoice->paymentRecord) {
                $invoice->paymentRecord->status = 'void';
                $invoice->paymentRecord->save();
            }
        });

        return ApiResponse::success(
            new InvoiceResource($invoice->fresh()),
            'Invoice marked as void'
        );
    }

    /**
     * Mark invoice as sent
     * POST /api/invoices/{id}/mark-sent
     */
    public function markSent(
        MarkInvoiceAsSentRequest $request,
        Invoice $invoice,
        InvoiceStatusService $statusService
    ) {
        if ($invoice->status === Invoice::STATUS_VOID) {
            return ApiResponse::error('Void invoice cannot be marked as sent', null, 400);
        }

        DB::transaction(function () use ($invoice, $request, $statusService) {
            $invoice->refresh();

            if ($invoice->status === Invoice::STATUS_VOID) {
                return;
            }

            $sentAt = !empty($request->input('manual_sent_at'))
                ? \Illuminate\Support\Carbon::parse($request->input('manual_sent_at'), 'Asia/Jakarta')
                : now('Asia/Jakarta');

            $invoice->email_sent = true;
            $invoice->sent_at = $sentAt;
            $invoice->last_error = null;
            $invoice->save();

            $note = trim((string) ($request->input('manual_note') ?? ''));
            $historyNote = $note !== '' ? 'Marked as sent via API: ' . $note : 'Marked as sent via API';

            if ($invoice->status !== Invoice::STATUS_PAID) {
                $statusService->transition($invoice, Invoice::STATUS_SENT, $historyNote, auth()->id());
            }
        });

        return ApiResponse::success(
            new InvoiceResource($invoice->fresh()),
            'Invoice marked as sent'
        );
    }

    /**
     * Record manual payment
     * POST /api/invoices/{id}/mark-as-paid
     */
    public function markAsPaid(
        RecordPaymentRequest $request,
        Invoice $invoice,
        InvoiceStatusService $statusService
    ) {
        if ($invoice->status === Invoice::STATUS_VOID) {
            return ApiResponse::error('Cannot mark void invoice as paid', null, 400);
        }

        if ($invoice->status === Invoice::STATUS_PAID) {
            return ApiResponse::error('Invoice is already marked as paid', null, 400);
        }

        DB::transaction(function () use ($invoice, $request, $statusService) {
            $invoice->refresh();

            $paidAt = !empty($request->input('paid_at'))
                ? \Illuminate\Support\Carbon::parse($request->input('paid_at'), 'Asia/Jakarta')
                : now('Asia/Jakarta');

            $invoice->paid_at = $paidAt;
            $invoice->save();

            $note = trim((string) ($request->input('payment_note') ?? ''));
            $historyNote = $note !== '' ? 'Marked as paid via API: ' . $note : 'Marked as paid manually via API';

            $statusService->transition($invoice, Invoice::STATUS_PAID, $historyNote, auth()->id());

            if ($invoice->paymentRecord) {
                $invoice->paymentRecord->status = 'paid';
                $invoice->paymentRecord->paid_at = $paidAt;
                $invoice->paymentRecord->save();
            }
        });

        return ApiResponse::success(
            new InvoiceResource($invoice->fresh()),
            'Invoice marked as paid'
        );
    }

    /**
     * Get invoice status history
     * GET /api/invoices/{id}/status-history
     */
    public function statusHistory(Invoice $invoice)
    {
        $invoice->load('statusHistories.performer');
        $histories = $invoice->statusHistories;

        return ApiResponse::success(
            StatusHistoryResource::collection($histories),
            'Invoice status history retrieved'
        );
    }

    /**
     * Download invoice PDF
     * GET /api/invoices/{id}/pdf
     */
    public function downloadPdf(Invoice $invoice)
    {
        if (!$invoice->generated_pdf_path || !file_exists(storage_path('app/' . $invoice->generated_pdf_path))) {
            return ApiResponse::error('PDF not available for this invoice', null, 404);
        }

        return response()->file(
            storage_path('app/' . $invoice->generated_pdf_path),
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Download stamped PDF
     * GET /api/invoices/{id}/stamped-pdf
     */
    public function downloadStampedPdf(Invoice $invoice)
    {
        if (!$invoice->stamped_pdf_path || !file_exists(storage_path('app/' . $invoice->stamped_pdf_path))) {
            return ApiResponse::error('Stamped PDF not available for this invoice', null, 404);
        }

        return response()->file(
            storage_path('app/' . $invoice->stamped_pdf_path),
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Helper: Build adhoc invoice number
     */
    protected function buildAdhocInvoiceNumber(): string
    {
        $year = now('Asia/Jakarta')->format('Y');
        $prefix = "INV/{$year}/";

        $maxSeq = 0;
        $numbers = Invoice::query()->where('invoice_number', 'like', $prefix . '%')->pluck('invoice_number');

        foreach ($numbers as $number) {
            if (preg_match('/^INV\/\d{4}\/(\d{5})$/', $number, $matches)) {
                $maxSeq = max($maxSeq, (int) $matches[1]);
            }
        }

        $nextSeq = $maxSeq + 1;
        do {
            $candidate = $prefix . str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);
            $nextSeq++;
        } while (Invoice::query()->where('invoice_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Helper: Create payment record for invoice
     */
    protected function createPaymentRecordForInvoice(Invoice $invoice, Client $client, XenditService $xenditService): void
    {
        $xendit = $xenditService->createPaymentLink([
            'external_id' => $invoice->invoice_number,
            'amount' => (float) $invoice->total_amount,
            'description' => 'Invoice ' . $invoice->invoice_number,
            'invoice_duration' => 60 * 60 * 24 * 30,
            'customer' => [
                'given_names' => $client->name,
                'email' => $client->email,
            ],
            'currency' => $invoice->currency,
            'success_redirect_url' => config('app.url'),
        ]);

        \App\Models\PaymentRecord::query()->create([
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
}
