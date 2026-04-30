<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\PaymentRecordResource;
use App\Models\PaymentRecord;
use App\Services\XenditService;
use Illuminate\Http\Request;

class PaymentRecordController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List all payment records
     * GET /api/payments
     */
    public function index(Request $request)
    {
        $query = PaymentRecord::query()->with('invoice');

        // Filter by status if provided
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Filter by provider if provided
        if ($provider = $request->query('provider')) {
            $query->where('provider', $provider);
        }

        // Filter by paid status
        if ($request->has('paid')) {
            if ($request->boolean('paid')) {
                $query->whereNotNull('paid_at');
            } else {
                $query->whereNull('paid_at');
            }
        }

        $perPage = $request->query('per_page', 20);
        $payments = $query->latest('updated_at')->paginate($perPage);

        return ApiResponse::paginated($payments, 'Payment records retrieved');
    }

    /**
     * Get single payment record
     * GET /api/payments/{id}
     */
    public function show(PaymentRecord $payment)
    {
        $payment->load('invoice');

        return ApiResponse::success(new PaymentRecordResource($payment), 'Payment record retrieved');
    }

    /**
     * Sync payment status with Xendit
     * POST /api/payments/{id}/sync-xendit
     */
    public function syncXendit(PaymentRecord $payment, XenditService $xenditService)
    {
        try {
            if ($payment->provider !== 'xendit') {
                return ApiResponse::error('This payment record is not from Xendit provider', null, 400);
            }

            $xenditStatus = $xenditService->getInvoiceStatus($payment->provider_reference);

            if ($xenditStatus && is_array($xenditStatus)) {
                $payment->status = strtolower($xenditStatus['status'] ?? 'pending');
                if (strtolower($xenditStatus['status'] ?? '') === 'paid' && !$payment->paid_at) {
                    $payment->paid_at = now('Asia/Jakarta');
                }
                $payment->payload = $xenditStatus;
                $payment->save();
            }

            return ApiResponse::success(
                new PaymentRecordResource($payment),
                'Payment status synced successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to sync payment: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get all payment records for an invoice
     * GET /api/invoices/{invoice_id}/payments
     */
    public function byInvoice(Request $request, $invoiceId)
    {
        $payments = PaymentRecord::query()
            ->where('invoice_id', $invoiceId)
            ->with('invoice')
            ->latest('updated_at')
            ->paginate($request->query('per_page', 20));

        return ApiResponse::paginated($payments, 'Invoice payment records retrieved');
    }
}
