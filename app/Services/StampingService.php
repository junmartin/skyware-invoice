<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class StampingService
{
    protected $statusService;
    protected $sendingService;

    public function __construct(InvoiceStatusService $statusService, InvoiceSendingService $sendingService)
    {
        $this->statusService = $statusService;
        $this->sendingService = $sendingService;
    }

    public function uploadStampedInvoice(Invoice $invoice, UploadedFile $file, $performedBy)
    {
        return DB::transaction(function () use ($invoice, $file, $performedBy) {
            $path = $file->storeAs('invoices/stamped', $invoice->invoice_number.'-stamped.pdf', 'local');

            $invoice->stamped_pdf_path = $path;
            $invoice->stamped_uploaded_at = now('Asia/Jakarta');
            $invoice->stamped_uploaded_by = $performedBy;
            $invoice->stamping_status = 'completed';
            $invoice->ready_to_send_at = now('Asia/Jakarta');
            $invoice->save();

            $this->statusService->transition($invoice, Invoice::STATUS_READY_TO_SEND, 'Stamped PDF uploaded and invoice is ready', $performedBy);
            $this->sendingService->maybeAutoSend($invoice, $performedBy);

            return $invoice;
        });
    }
}
