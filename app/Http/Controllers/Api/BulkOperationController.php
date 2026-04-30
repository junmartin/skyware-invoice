<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Models\Invoice;
use App\Services\InvoiceSendingService;
use Illuminate\Http\Request;

class BulkOperationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Send all ready invoices
     * POST /api/bulk/send-all-ready
     */
    public function sendAllReady(InvoiceSendingService $service)
    {
        try {
            $count = $service->sendReadyInvoices(auth()->id());

            return ApiResponse::success([
                'sent_count' => $count,
                'message' => sprintf('Successfully sent %d invoices', $count),
            ], 'Bulk send operation completed');
        } catch (\Exception $e) {
            return ApiResponse::error('Bulk send operation failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Sync all payment statuses from Xendit
     * POST /api/bulk/sync-payments
     */
    public function syncAllPayments(Request $request)
    {
        try {
            $xenditService = app(\App\Services\XenditService::class);
            $statusService = app(\App\Services\InvoiceStatusService::class);

            // Get all invoices that are not yet paid or failed
            $invoices = Invoice::query()
                ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_READY_TO_SEND, Invoice::STATUS_SENDING])
                ->with(['paymentRecord'])
                ->get();

            $syncedCount = 0;
            $updatedCount = 0;

            foreach ($invoices as $invoice) {
                if (!$invoice->paymentRecord || $invoice->paymentRecord->provider !== 'xendit') {
                    continue;
                }

                try {
                    $xenditStatus = $xenditService->getInvoiceStatus($invoice->paymentRecord->provider_reference);

                    if ($xenditStatus && is_array($xenditStatus)) {
                        $newStatus = strtolower($xenditStatus['status'] ?? 'pending');

                        if ($newStatus === 'paid' && $invoice->status !== Invoice::STATUS_PAID) {
                            $invoice->paid_at = now('Asia/Jakarta');
                            $invoice->save();
                            $statusService->transition($invoice, Invoice::STATUS_PAID, 'Marked paid by Xendit sync', auth()->id());
                            $updatedCount++;
                        }

                        $invoice->paymentRecord->status = $newStatus;
                        $invoice->paymentRecord->payload = $xenditStatus;
                        $invoice->paymentRecord->save();
                    }

                    $syncedCount++;
                } catch (\Exception $e) {
                    // Log individual sync errors but continue with others
                    \Illuminate\Support\Facades\Log::warning('Failed to sync payment for invoice ' . $invoice->id . ': ' . $e->getMessage());
                }
            }

            return ApiResponse::success([
                'total_synced' => $syncedCount,
                'invoices_updated' => $updatedCount,
                'message' => sprintf('Synced %d payment records, updated %d invoices', $syncedCount, $updatedCount),
            ], 'Payment sync completed');
        } catch (\Exception $e) {
            return ApiResponse::error('Bulk sync operation failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Bulk void invoices
     * POST /api/bulk/void-invoices
     */
    public function bulkVoid(Request $request)
    {
        $validated = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'integer|exists:invoices,id',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $statusService = app(\App\Services\InvoiceStatusService::class);
            $voidedCount = 0;
            $reason = trim($validated['reason'] ?? 'Bulk voided via API');

            foreach ($validated['invoice_ids'] as $invoiceId) {
                $invoice = Invoice::find($invoiceId);

                if ($invoice && $invoice->status !== Invoice::STATUS_VOID) {
                    $statusService->transition($invoice, Invoice::STATUS_VOID, $reason, auth()->id());

                    if ($invoice->paymentRecord) {
                        $invoice->paymentRecord->status = 'void';
                        $invoice->paymentRecord->save();
                    }

                    $voidedCount++;
                }
            }

            return ApiResponse::success([
                'requested_count' => count($validated['invoice_ids']),
                'voided_count' => $voidedCount,
                'message' => sprintf('Voided %d out of %d invoices', $voidedCount, count($validated['invoice_ids'])),
            ], 'Bulk void operation completed');
        } catch (\Exception $e) {
            return ApiResponse::error('Bulk void operation failed: ' . $e->getMessage(), null, 500);
        }
    }
}
