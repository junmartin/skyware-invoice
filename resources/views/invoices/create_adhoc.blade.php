@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Create Adhoc Invoice</h1>
        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary btn-sm">Back to Invoices</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('invoices.adhoc.store') }}" class="row g-3">
                @csrf

                <div class="col-md-6">
                    <label class="form-label" for="client_id">Client</label>
                    <select class="form-select" id="client_id" name="client_id" required>
                        <option value="">Select Client</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @if(old('client_id') == $client->id) selected @endif>
                                {{ $client->name }} ({{ $client->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="amount">Amount</label>
                    <input type="number" step="0.01" min="1" class="form-control" id="amount" name="amount" value="{{ old('amount') }}" required>
                </div>

                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <input type="text" class="form-control" id="description" name="description" value="{{ old('description') }}" placeholder="One-time service invoice" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="issue_date">Issue Date</label>
                    <input type="date" class="form-control" id="issue_date" name="issue_date" value="{{ old('issue_date', $today) }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="due_date">Due Date</label>
                    <input type="date" class="form-control" id="due_date" name="due_date" value="{{ old('due_date', $today) }}" required>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="add_stamp_duty" name="add_stamp_duty" @if(old('add_stamp_duty')) checked @endif>
                        <label class="form-check-label" for="add_stamp_duty">
                            Add stamp duty on this invoice (+Rp10.000)
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="save_as_draft" name="save_as_draft" @if(old('save_as_draft')) checked @endif>
                        <label class="form-check-label" for="save_as_draft">
                            Save as draft first (confirm later)
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <div class="alert alert-info mb-0">
                        No VAT is added for now. If you tick stamp duty, a separate detail line (Rp10.000) is added and the invoice goes to stamping queue after confirmation.
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Create Adhoc Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
