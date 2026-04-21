@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between mb-3">
        <h1 class="h4">Invoice History</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('invoices.adhoc.create') }}" class="btn btn-outline-primary btn-sm">Create Adhoc Invoice</a>
            <form method="POST" action="{{ route('invoices.send-all-ready') }}">@csrf<button class="btn btn-success btn-sm">Send All Ready</button></form>
        </div>
    </div>

    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-2">
            <select name="client_id" class="form-select">
                <option value="">All Clients</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" @if(request('client_id') == $client->id) selected @endif>{{ $client->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><input class="form-control" type="text" name="status" value="{{ request('status') }}" placeholder="Status"></div>
        <div class="col-md-2">
            <select name="cycle" class="form-select">
                <option value="">All Cycles</option>
                @foreach($cycles as $cycle)
                    <option value="{{ sprintf('%04d-%02d',$cycle->year,$cycle->month) }}" @if(request('cycle') == sprintf('%04d-%02d',$cycle->year,$cycle->month)) selected @endif>{{ sprintf('%04d-%02d',$cycle->year,$cycle->month) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><input class="form-control" type="date" name="from" value="{{ request('from') }}"></div>
        <div class="col-md-2"><input class="form-control" type="date" name="to" value="{{ request('to') }}"></div>
        <div class="col-md-2 d-flex align-items-center gap-2">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="show_void" id="show_void" value="1" @if(request()->boolean('show_void')) checked @endif>
                <label class="form-check-label small" for="show_void">Show void</label>
            </div>
            <button class="btn btn-outline-primary">Apply</button>
        </div>
    </form>

    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>Invoice</th><th>Client</th><th>Cycle</th><th>Status</th>
                <th>
                    @php
                        $nextDir = request('sort_dir') === 'asc' ? 'desc' : 'asc';
                        $arrow = request('sort_dir') === 'asc' ? '▲' : '▼';
                    @endphp
                    <a href="{{ request()->fullUrlWithQuery(['sort_dir' => $nextDir, 'page' => 1]) }}" class="text-decoration-none text-dark">Date {{ $arrow }}</a>
                </th>
                <th class="text-end">Total</th><th>Sent</th><th>Paid</th><th>Xendit</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
        @forelse($invoices as $invoice)
            <tr>
                <td>{{ $invoice->invoice_number }}</td>
                <td>{{ $invoice->client->name }}</td>
                <td>{{ $invoice->billingCycle ? sprintf('%04d-%02d', $invoice->billingCycle->year, $invoice->billingCycle->month) : '—' }}</td>
                <td>
                    @if($invoice->status === 'void')
                        <span class="badge bg-danger">void</span>
                    @elseif($invoice->status === 'paid')
                        <span class="badge bg-success">paid</span>
                    @elseif($invoice->status === 'sent')
                        <span class="badge bg-info text-dark">sent</span>
                    @else
                        <span class="badge bg-secondary">{{ $invoice->status }}</span>
                    @endif
                </td>
                <td>{{ optional($invoice->issue_date)->format('Y-m-d') }}</td>
                <td class="text-end">{{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td>
                <td>{{ optional($invoice->sent_at)->format('Y-m-d H:i:s') }}</td>
                <td>{{ optional($invoice->paid_at)->format('Y-m-d H:i:s') }}</td>
                <td>{{ optional($invoice->paymentRecord)->status }}</td>
                <td><a class="btn btn-sm btn-outline-secondary" href="{{ route('invoices.show', $invoice) }}">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="10">No invoices found.</td></tr>
        @endforelse
        </tbody>
    </table>

    {{ $invoices->links('pagination::bootstrap-4') }}
</div>
@endsection
