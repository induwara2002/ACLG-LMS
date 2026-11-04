@extends('layouts.admin')

@section('title', __('Reports'))

@section('content')
<div class="card">
    <div class="card-body">
        <h2 class="page-title">{{ __('Reports') }}</h2>
        <p class="page-subtitle">{{ __('View operational and member reports') }}</p>
        <div class="alert alert-info">{{ __('Reports and analytics will be implemented in a later phase.') }}</div>
    </div>
</div>
@endsection
