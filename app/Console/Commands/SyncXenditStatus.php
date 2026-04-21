<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PaymentRecord;
use App\Services\InvoiceStatusService;
use App\Services\XenditService;
use Illuminate\Console\Command;

class SyncXenditStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:sync-xendit-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync pending Xendit payment links and update invoice paid state';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /** @var XenditService $xendit */
        $xendit = app(XenditService::class);
        /** @var InvoiceStatusService $statusService */
        $statusService = app(InvoiceStatusService::class);
        $updated = 0;

        PaymentRecord::query()->where('provider', 'xendit')->where('status', '!=', 'paid')->chunkById(100, function ($records) use (&$updated, $xendit, $statusService) {
            foreach ($records as $record) {
                if (! $record->external_id) {
                    continue;
                }

                $payload = $xendit->getInvoiceStatus($record->external_id);
                if (! is_array($payload) || empty($payload[0])) {
                    continue;
                }

                $item = $payload[0];
                $status = strtolower($item['status'] ?? 'pending');

                $record->status = $status;
                $record->payload = $item;
                $record->paid_at = $status === 'paid' ? now('Asia/Jakarta') : null;
                $record->save();

                if ($status === 'paid') {
                    $invoice = Invoice::query()->find($record->invoice_id);
                    if ($invoice && ! $invoice->paid_at) {
                        $invoice->paid_at = now('Asia/Jakarta');
                        $invoice->save();
                        $statusService->transition($invoice, Invoice::STATUS_PAID, 'Marked paid by sync command');
                    }
                }

                $updated++;
            }
        });

        $this->info('Synced records: '.$updated);
        return 0;
    }
}
