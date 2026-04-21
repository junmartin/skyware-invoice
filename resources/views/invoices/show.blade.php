@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Invoice {{ $invoice->invoice_number }}</h1>
        <div class="d-flex gap-2">
            @if($invoice->invoice_type === 'adhoc' && $invoice->status === 'generated' && !$invoice->paymentRecord)
                <form method="POST" action="{{ route('invoices.confirm-draft', $invoice) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">Confirm Draft</button>
                </form>
            @endif
            <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($invoice->status !== 'void')
        <div class="card mb-3 border-danger">
            <div class="card-body">
                <h6 class="mb-2 text-danger">Void Invoice</h6>
                <form method="POST" action="{{ route('invoices.void', $invoice) }}" class="row g-2" onsubmit="return confirm('Mark this invoice as void?');">
                    @csrf
                    <div class="col-md-9">
                        <input type="text" class="form-control" name="void_reason" maxlength="500" placeholder="Reason (optional)">
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-danger">Mark as Void</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-3 border-primary">
            <div class="card-body">
                <h6 class="mb-2 text-primary">Manual Status Update</h6>
                <form method="POST" action="{{ route('invoices.mark-sent', $invoice) }}" class="row g-2">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label" for="manual_sent_at">Sent At (optional)</label>
                        <input type="datetime-local" class="form-control" id="manual_sent_at" name="manual_sent_at" value="{{ old('manual_sent_at') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="manual_note">Note (optional)</label>
                        <input type="text" class="form-control" id="manual_note" name="manual_note" maxlength="500" placeholder="Sent manually outside system">
                    </div>
                    <div class="col-md-2 d-grid align-items-end">
                        <button type="submit" class="btn btn-primary mt-md-4">Mark as Sent</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card mb-3"><div class="card-header">Invoice Info</div><div class="card-body">
                <p><strong>Client:</strong> {{ $invoice->client->name }} ({{ $invoice->client->code }})</p>
                <p><strong>Status:</strong> {{ $invoice->status }}</p>
                <p><strong>Generated:</strong> {{ optional($invoice->generated_at)->format('Y-m-d H:i:s') }}</p>
                <p><strong>Ready to send:</strong> {{ optional($invoice->ready_to_send_at)->format('Y-m-d H:i:s') }}</p>
                <p><strong>Sent:</strong> {{ optional($invoice->sent_at)->format('Y-m-d H:i:s') }}</p>
                <p><strong>Paid:</strong> {{ optional($invoice->paid_at)->format('Y-m-d H:i:s') }}</p>
                <p><strong>Stamping:</strong> {{ $invoice->stamping_status }}</p>
                <p><strong>Stamped uploaded at:</strong> {{ optional($invoice->stamped_uploaded_at)->format('Y-m-d H:i:s') }}</p>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3"><div class="card-header">Payment</div><div class="card-body">
                <p><strong>Provider:</strong> {{ optional($invoice->paymentRecord)->provider }}</p>
                <p><strong>Status:</strong> {{ optional($invoice->paymentRecord)->status }}</p>
                <p><strong>External ID:</strong> {{ optional($invoice->paymentRecord)->external_id }}</p>
                @if(optional($invoice->paymentRecord)->payment_url)
                    <p><a href="{{ $invoice->paymentRecord->payment_url }}" target="_blank" rel="noopener">Open payment link</a></p>
                @endif
            </div></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Invoice Details</div>
        <table class="table table-sm mb-0">
            <thead><tr><th>#</th><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
            <tbody>
            @foreach($invoice->details as $detail)
                <tr>
                    <td>{{ $detail->position }}</td>
                    <td>{{ $detail->description }}</td>
                    <td>{{ $detail->quantity }}</td>
                    <td>{{ number_format($detail->unit_price, 2) }}</td>
                    <td>{{ number_format($detail->line_total, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card mb-3">
        <div class="card-header">Email Logs</div>
        <table class="table table-sm mb-0">
            <thead><tr><th>Attempt</th><th>Status</th><th>Recipient</th><th>Sent At</th><th>Error</th></tr></thead>
            <tbody>
            @forelse($invoice->emailLogs as $log)
                <tr>
                    <td>{{ optional($log->attempted_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->status }}</td>
                    <td>{{ $log->recipient }}</td>
                    <td>{{ optional($log->sent_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->error_message }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No email logs yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header">Status Timeline</div>
        <table class="table table-sm mb-0">
            <thead><tr><th>Time</th><th>Status</th><th>Note</th><th>By</th></tr></thead>
            <tbody>
            @forelse($invoice->statusHistories as $history)
                <tr>
                    <td>{{ optional($history->created_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $history->status }}</td>
                    <td>{{ $history->note }}</td>
                    <td>{{ optional($history->performer)->name }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No timeline entries.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
