@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="h4 mb-3">Stamping Queue</h1>
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

    <table class="table table-bordered table-sm">
        <thead><tr><th>Invoice</th><th>Client</th><th>Generated At</th><th>Upload Stamped PDF</th></tr></thead>
        <tbody>
        @forelse($invoices as $invoice)
            <tr>
                <td>{{ $invoice->invoice_number }}</td>
                <td>{{ $invoice->client->name }}</td>
                <td>{{ optional($invoice->generated_at)->format('Y-m-d H:i:s') }}</td>
                <td>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('stamping.upload', $invoice) }}">
                        @csrf
                        <div class="input-group">
                            <input type="file" name="stamped_pdf" class="form-control" required accept="application/pdf">
                            <button class="btn btn-primary">Upload</button>
                        </div>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4">No pending stamping invoices.</td></tr>
        @endforelse
        </tbody>
    </table>

    {{ $invoices->links() }}
</div>
@endsection
