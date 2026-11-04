@extends('layouts.admin')

@section('title', __('Overdue Books'))

@section('content')
<div class="card">
    <div class="card-body">
        <h2 class="page-title">{{ __('Overdue Books') }}</h2>
        <p class="page-subtitle">{{ __('Monitor overdue borrowing records') }}</p>
        <div class="alert alert-info">{{ __('Overdue logic will be implemented in a later phase.') }}</div>
    </div>
</div>
@endsection
