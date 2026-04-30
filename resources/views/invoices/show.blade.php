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
            <a href="{{ route('invoices.download-pdf', $invoice) }}" class="btn btn-sm btn-outline-primary">Generate PDF</a>
            <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <h6 class="mb-2">Recipient</h6>
            <form method="POST" action="{{ route('invoices.update-recipient', $invoice) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-8">
                    <label class="form-label" for="recipient_email">Recipient Email</label>
                    <input
                        type="email"
                        class="form-control"
                        id="recipient_email"
                        name="recipient_email"
                        value="{{ old('recipient_email', $invoice->recipient_email ?: $defaultRecipientEmail ?: $invoice->client->email) }}"
                        required
                    >
                    <small class="text-muted">Default from settings is used automatically, but you can override it here per invoice.</small>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-primary">Update Recipient</button>
                </div>
            </form>
        </div>
    </div>

    @if($invoice->status !== 'void')
        <div class="card mb-3 border-primary">
            <div class="card-body">
                <h6 class="mb-2 text-primary">Status Update</h6>
                <form method="POST" action="{{ route('invoices.update-status', $invoice) }}" class="row g-2" id="singleStatusForm" onsubmit="return confirm('Apply status update to this invoice?');">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label" for="status_target">Status</label>
                        <select class="form-select" id="status_target" name="status_target" required>
                            <option value="">Select status...</option>
                            <option value="void">Void</option>
                            <option value="sent">Sent</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-none" id="status_datetime_wrap">
                        <label class="form-label" for="status_datetime">Date & Time</label>
                        <input type="datetime-local" class="form-control" id="status_datetime" name="status_datetime" value="{{ old('status_datetime') }}">
                    </div>
                    <div class="col-md-3 d-none" id="payment_method_wrap">
                        <label class="form-label" for="payment_method">Payment Method</label>
                        <input type="text" class="form-control" id="payment_method" name="payment_method" maxlength="100" placeholder="Bank transfer / cash / etc">
                    </div>
                    <div class="col-md-3" id="status_note_wrap">
                        <label class="form-label" for="status_note" id="status_note_label">Reason / Note</label>
                        <input type="text" class="form-control" id="status_note" name="status_note" maxlength="500" placeholder="Optional">
                    </div>
                    <div class="col-md-2 d-grid align-items-end">
                        <button type="submit" class="btn btn-primary mt-md-4">Apply</button>
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
                <p><strong>Recipient:</strong> {{ $invoice->recipient_email ?: $defaultRecipientEmail ?: $invoice->client->email }}</p>
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
                <p><strong>Method:</strong> {{ data_get(optional($invoice->paymentRecord)->payload, 'manual_payment_method') }}</p>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusTarget = document.getElementById('status_target');
    const datetimeWrap = document.getElementById('status_datetime_wrap');
    const datetimeInput = document.getElementById('status_datetime');
    const methodWrap = document.getElementById('payment_method_wrap');
    const methodInput = document.getElementById('payment_method');
    const noteLabel = document.getElementById('status_note_label');

    if (!statusTarget) {
        return;
    }

    statusTarget.addEventListener('change', function () {
        const value = statusTarget.value;
        const needsDatetime = value === 'sent' || value === 'paid';
        const needsMethod = value === 'paid';

        datetimeWrap.classList.toggle('d-none', !needsDatetime);
        methodWrap.classList.toggle('d-none', !needsMethod);

        datetimeInput.required = needsDatetime;
        methodInput.required = needsMethod;

        if (value === 'void') {
            noteLabel.textContent = 'Reason';
        } else if (value === 'sent') {
            noteLabel.textContent = 'Note';
        } else if (value === 'paid') {
            noteLabel.textContent = 'Note (optional)';
        } else {
            noteLabel.textContent = 'Reason / Note';
        }
    });
});
</script>
@endsection
