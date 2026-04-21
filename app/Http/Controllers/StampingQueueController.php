<?php

namespace App\Http\Controllers;

use App\Http\Requests\StampedInvoiceUploadRequest;
use App\Models\Invoice;
use App\Services\StampingService;
use Illuminate\Http\Request;

class StampingQueueController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $invoices = Invoice::query()
            ->with(['client', 'billingCycle'])
            ->where('status', Invoice::STATUS_PENDING_STAMPING)
            ->latest('generated_at')
            ->paginate(25);

        return view('stamping.index', compact('invoices'));
    }

    public function upload(StampedInvoiceUploadRequest $request, Invoice $invoice, StampingService $stampingService)
    {
        if (! $invoice->stamping_required) {
            return back()->withErrors(['stamped_pdf' => 'Invoice does not require stamping.']);
        }

        $stampingService->uploadStampedInvoice($invoice, $request->file('stamped_pdf'), auth()->id());

        return redirect()->route('stamping.index')->with('status', 'Stamped PDF uploaded.');
    }
}
