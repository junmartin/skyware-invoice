@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="h4">Settings</h1>
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

    <div class="card mb-3"><div class="card-body">
        <form method="POST" action="{{ route('settings.update') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label">Sending Mode</label>
                <select class="form-select" name="send_mode">
                    <option value="auto" @if($sendMode === 'auto') selected @endif>Auto-send when ready</option>
                    <option value="manual" @if($sendMode === 'manual') selected @endif>Manual-send (wait command)</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Default Recipient Email</label>
                <input type="email" name="default_recipient_email" class="form-control" value="{{ old('default_recipient_email', $defaultRecipientEmail) }}" placeholder="billing@example.com">
            </div>
            <div class="col-md-2"><button class="btn btn-primary">Save</button></div>
        </form>
    </div></div>

    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('settings.send-all-ready') }}">
            @csrf
            <button class="btn btn-success">Send all ready invoices now</button>
        </form>
    </div></div>
</div>
@endsection
