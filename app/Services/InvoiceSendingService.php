<?php

namespace App\Services;

use App\Mail\InvoiceReadyMail;
use App\Models\EmailLog;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class InvoiceSendingService
{
    protected $statusService;

    public function __construct(InvoiceStatusService $statusService)
    {
        $this->statusService = $statusService;
    }

    public function sendReadyInvoices($performedBy = null)
    {
        $sent = 0;

        Invoice::query()->readyToSend()->with(['client', 'paymentRecord'])->chunkById(100, function ($invoices) use (&$sent, $performedBy) {
            foreach ($invoices as $invoice) {
                if ($this->sendSingleInvoice($invoice, $performedBy)) {
                    $sent++;
                }
            }
        });

        return $sent;
    }

    public function sendSingleInvoice(Invoice $invoice, $performedBy = null)
    {
        if ($invoice->email_sent || $invoice->status !== Invoice::STATUS_READY_TO_SEND) {
            return false;
        }

        $attachmentPath = $this->resolveInvoiceAttachment($invoice);
        if (! $attachmentPath || ! Storage::disk('local')->exists($attachmentPath)) {
            $invoice->last_error = 'Invoice attachment not found';
            $invoice->save();
            return false;
        }

        return DB::transaction(function () use ($invoice, $attachmentPath, $performedBy) {
            $invoice->refresh();
            if ($invoice->email_sent || $invoice->status !== Invoice::STATUS_READY_TO_SEND) {
                return false;
            }

            $this->statusService->transition($invoice, Invoice::STATUS_SENDING, 'Sending invoice email', $performedBy);

            $attachments = [
                [
                    'type' => 'invoice_pdf',
                    'path' => storage_path('app/'.$attachmentPath),
                    'name' => basename($attachmentPath),
                    'mime' => 'application/pdf',
                ],
            ];

            $jneibbPath = $this->resolveJneibbUsagePath($invoice);
            if ($jneibbPath) {
                $attachments[] = [
                    'type' => 'usage_xlsx',
                    'path' => $jneibbPath,
                    'name' => basename($jneibbPath),
                    'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ];
            }

            $recipient = $this->resolveRecipientEmail($invoice);

            $emailLog = EmailLog::query()->create([
                'invoice_id' => $invoice->id,
                'status' => 'attempted',
                'recipient' => $recipient,
                'subject' => 'Invoice '.$invoice->invoice_number,
                'attachment_types' => array_column($attachments, 'type'),
                'attempted_at' => now('Asia/Jakarta'),
            ]);

            try {
                Mail::to($recipient)->send(new InvoiceReadyMail($invoice, $attachments));

                $invoice->email_sent = true;
                $invoice->sent_at = now('Asia/Jakarta');
                $invoice->last_error = null;
                $invoice->save();

                $emailLog->status = 'sent';
                $emailLog->sent_at = now('Asia/Jakarta');
                $emailLog->save();

                $this->statusService->transition($invoice, Invoice::STATUS_SENT, 'Invoice email sent', $performedBy);
                Log::info('billing.send.invoice_sent', ['invoice_id' => $invoice->id]);

                return true;
            } catch (\Throwable $e) {
                $invoice->status = Invoice::STATUS_FAILED;
                $invoice->last_error = $e->getMessage();
                $invoice->save();

                $emailLog->status = 'failed';
                $emailLog->error_message = $e->getMessage();
                $emailLog->save();

                $this->statusService->transition($invoice, Invoice::STATUS_FAILED, 'Email send failed: '.$e->getMessage(), $performedBy);
                Log::error('billing.send.invoice_failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);

                return false;
            }
        });
    }

    public function maybeAutoSend(Invoice $invoice, $performedBy = null)
    {
        $mode = \App\Models\AppSetting::getValue('send_mode', 'manual');

        if ($mode === 'auto') {
            $this->sendSingleInvoice($invoice, $performedBy);
        }
    }

    protected function resolveInvoiceAttachment(Invoice $invoice)
    {
        if ($invoice->stamping_required) {
            return $invoice->stamped_pdf_path;
        }

        return $invoice->generated_pdf_path;
    }

    protected function resolveJneibbUsagePath(Invoice $invoice)
    {
        if (strtolower($invoice->client->code) !== 'jneibb') {
            return null;
        }

        $configured = $invoice->client->usage_xlsx_path;
        if ($configured && file_exists($configured)) {
            return $configured;
        }

        $baseDir = rtrim(config('billing.jneibb_usage_base_path'), DIRECTORY_SEPARATOR);
        $pattern = $baseDir.DIRECTORY_SEPARATOR.'*'.strtolower($invoice->client->code).'*.xlsx';
        $matches = glob($pattern);

        return $matches ? $matches[0] : null;
    }

    protected function resolveRecipientEmail(Invoice $invoice): string
    {
        $recipient = trim((string) ($invoice->recipient_email ?? ''));
        if ($recipient !== '') {
            return $recipient;
        }

        $defaultRecipient = trim((string) \App\Models\AppSetting::getValue('default_recipient_email', ''));
        if ($defaultRecipient !== '') {
            return $defaultRecipient;
        }

        return (string) $invoice->client->email;
    }
}
