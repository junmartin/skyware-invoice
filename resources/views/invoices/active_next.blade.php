@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="h4">Active Clients - Next Billing Cycle {{ $nextCycle->label }}</h1>

    <form class="row g-2 mb-3" method="GET" action="{{ route('invoices.active-next') }}">
        <div class="col-md-4">
            <input name="q" value="{{ $q }}" class="form-control" placeholder="Search by name/code">
        </div>
        <div class="col-md-2"><button class="btn btn-outline-primary">Filter</button></div>
    </form>

    <table class="table table-bordered table-sm">
        <thead><tr><th>Code</th><th>Name</th><th>Email</th><th>Last Billed</th><th>Next Due</th></tr></thead>
        <tbody>
        @forelse($clients as $client)
            <tr>
                <td>{{ $client->code }}</td>
                <td>{{ $client->name }}</td>
                <td>{{ $client->email }}</td>
                <td>{{ optional($client->last_billed_at)->format('Y-m-d H:i') }}</td>
                <td>{{ $nextCycle->cycle_start_date->format('Y-m-d') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No active clients</td></tr>
        @endforelse
        </tbody>
    </table>

    {{ $clients->links() }}
</div>
@endsection
