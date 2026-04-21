@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="h4">Edit Client</h1>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('clients.update', $client) }}">
        @method('PUT')
        @include('clients._form')
    </form>
</div>
@endsection
