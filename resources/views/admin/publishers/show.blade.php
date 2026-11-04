@extends('layouts.admin')

@section('title', __('Publisher Details'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ __('Publisher Details') }}</h1>
        <p class="page-subtitle">{{ __('Publisher profile') }}</p>
    </div>
    <div class="btn-group">
        @unless (auth()->user()->isMainAdmin())
            <a href="{{ route('admin.publishers.edit', $publisher) }}" class="btn btn-primary">{{ __('Edit') }}</a>
        @endunless
        <a href="{{ route('admin.publishers.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6"><strong>ID:</strong> {{ $publisher->id }}</div>
            <div class="col-md-6"><strong>{{ __('Name') }}:</strong> {{ $publisher->name }}</div>
            <div class="col-md-6"><strong>{{ __('Phone') }}:</strong> {{ $publisher->phone ?? '—' }}</div>
            <div class="col-md-6"><strong>{{ __('Email') }}:</strong> {{ $publisher->email ?? '—' }}</div>
            <div class="col-12"><strong>{{ __('Address') }}:</strong> {{ $publisher->address ?? '—' }}</div>
        </div>
    </div>
</div>
@endsection
