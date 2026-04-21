<?php

namespace App\Console\Commands;

use App\Services\InvoiceSendingService;
use Illuminate\Console\Command;

class SendReadyInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:send-ready';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send all invoices that are ready_to_send and not sent yet';

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
        /** @var InvoiceSendingService $service */
        $service = app(InvoiceSendingService::class);
        $count = $service->sendReadyInvoices();

        $this->info('Sent ready invoices: '.$count);

        return 0;
    }
}
