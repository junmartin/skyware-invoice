@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between mb-3">
        <h1 class="h4">Clients</h1>
        <a class="btn btn-primary btn-sm" href="{{ route('clients.create') }}">New Client</a>
    </div>

    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

    <form class="row g-2 mb-3" method="GET">
        <div class="col-md-4"><input class="form-control" name="q" value="{{ $q }}" placeholder="Search by code/name"></div>
        <div class="col-md-2"><button class="btn btn-outline-primary">Search</button></div>
    </form>

    <table class="table table-bordered table-sm">
        <thead><tr><th>Code</th><th>Name</th><th>Email</th><th>Active</th><th>Last Billed</th><th></th></tr></thead>
        <tbody>
        @foreach($clients as $client)
            <tr>
                <td>{{ $client->code }}</td>
                <td>{{ $client->name }}</td>
                <td>{{ $client->email }}</td>
                <td>{{ $client->is_active ? 'Yes' : 'No' }}</td>
                <td>{{ optional($client->last_billed_at)->format('Y-m-d H:i:s') }}</td>
                <td><a href="{{ route('clients.edit', $client) }}" class="btn btn-sm btn-outline-secondary">Edit</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{ $clients->links() }}
</div>
@endsection
