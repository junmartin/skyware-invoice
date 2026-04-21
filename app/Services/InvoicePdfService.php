<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function generate(Invoice $invoice)
    {
        $pdf = PDF::loadView('pdf.invoice', ['invoice' => $invoice->load('client', 'billingCycle', 'details', 'paymentRecord')]);

        $path = sprintf('invoices/generated/%s.pdf', $invoice->invoice_number);
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
