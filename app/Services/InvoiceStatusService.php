<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceStatusHistory;

class InvoiceStatusService
{
    public function transition(Invoice $invoice, $status, $note = null, $performedBy = null)
    {
        $invoice->status = $status;
        $invoice->save();

        InvoiceStatusHistory::query()->create([
            'invoice_id' => $invoice->id,
            'status' => $status,
            'note' => $note,
            'performed_by' => $performedBy,
        ]);
    }
}
