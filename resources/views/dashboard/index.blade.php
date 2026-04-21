@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Billing Dashboard</h1>
        <form method="POST" action="{{ route('invoices.generate-next-cycle') }}">
            @csrf
            <button class="btn btn-primary btn-sm">Generate Next Cycle</button>
        </form>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <h6>Next Cycle</h6>
                <div>{{ $nextCycle ? $nextCycle->label : 'Not generated yet' }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <h6>Pending Stamping</h6>
                <div>{{ $pendingStampingCount }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <h6>Ready To Send</h6>
                <div>{{ $statusCounts['ready_to_send'] ?? 0 }}</div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Invoice Status Summary</div>
        <div class="card-body">
            <table class="table table-sm table-bordered mb-0">
                <thead><tr><th>Status</th><th>Count</th></tr></thead>
                <tbody>
                @forelse($statusCounts as $status => $count)
                    <tr><td>{{ $status }}</td><td>{{ $count }}</td></tr>
                @empty
                    <tr><td colspan="2">No invoice data</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
