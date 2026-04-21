<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class XenditService
{
    public function createPaymentLink(array $payload)
    {
        $secret = (string) config('services.xendit.secret_key', '');
        $response = Http::withBasicAuth($secret, '')
            ->post(config('services.xendit.base_url').'/v2/invoices', $payload);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    public function getInvoiceStatus($externalId)
    {
        $secret = (string) config('services.xendit.secret_key', '');
        $response = Http::withBasicAuth($secret, '')
            ->get(config('services.xendit.base_url').'/v2/invoices', ['external_id' => $externalId]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    public function verifyWebhookToken($token)
    {
        $expected = config('services.xendit.webhook_token');

        if (! $expected) {
            return true;
        }

        return hash_equals($expected, (string) $token);
    }
}
