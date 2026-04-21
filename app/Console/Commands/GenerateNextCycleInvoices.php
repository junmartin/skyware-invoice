<?php

namespace App\Console\Commands;

use App\Services\InvoiceGenerationService;
use Illuminate\Console\Command;

class GenerateNextCycleInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:generate-next-cycle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate invoices for the upcoming billing cycle';

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
        /** @var InvoiceGenerationService $service */
        $service = app(InvoiceGenerationService::class);
        list($cycle, $created) = $service->generateNextCycle();

        $this->info(sprintf('Billing cycle %04d-%02d generated. New invoices: %d', $cycle->year, $cycle->month, $created));

        return 0;
    }
}
