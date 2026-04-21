<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentRecord;
use App\Services\InvoiceStatusService;
use App\Services\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function xenditInvoicePaid(Request $request, XenditService $xenditService, InvoiceStatusService $statusService)
    {
        $token = $request->header('x-callback-token');
        if (! $xenditService->verifyWebhookToken($token)) {
            Log::warning('webhook.xendit.invalid_token');
            return response()->json(['message' => 'invalid token'], 401);
        }

        $payload = $request->validate([
            'external_id' => 'required|string',
            'id' => 'nullable|string',
            'status' => 'required|string',
            'paid_at' => 'nullable|string',
            'amount' => 'nullable',
        ]);

        $payment = PaymentRecord::query()->where('external_id', $payload['external_id'])->first();
        if (! $payment) {
            Log::warning('webhook.xendit.payment_not_found', ['external_id' => $payload['external_id']]);
            return response()->json(['message' => 'ok']);
        }

        $payment->provider_reference = $payload['id'] ?? $payment->provider_reference;
        $payment->status = strtolower($payload['status']);
        $payment->paid_at = strtolower($payload['status']) === 'paid' ? now('Asia/Jakarta') : null;
        $payment->payload = $request->all();
        $payment->save();

        if (strtolower($payload['status']) === 'paid') {
            $invoice = Invoice::query()->find($payment->invoice_id);
            if ($invoice && ! $invoice->paid_at) {
                $invoice->paid_at = now('Asia/Jakarta');
                $invoice->save();
                $statusService->transition($invoice, Invoice::STATUS_PAID, 'Marked paid by Xendit webhook');
            }
        }

        Log::info('webhook.xendit.processed', ['external_id' => $payload['external_id'], 'status' => $payload['status']]);

        return response()->json(['message' => 'ok']);
    }
}
