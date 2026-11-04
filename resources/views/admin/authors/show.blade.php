@extends('layouts.admin')

@section('title', __('Author Details'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ __('Author Details') }}</h1>
        <p class="page-subtitle">{{ __('Author profile and associated books') }}</p>
    </div>
    <div class="btn-group">
        @unless (auth()->user()->isMainAdmin())
            <a href="{{ route('admin.authors.edit', $author) }}" class="btn btn-primary">{{ __('Edit') }}</a>
        @endunless
        <a href="{{ route('admin.authors.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6"><strong>ID:</strong> {{ $author->id }}</div>
            <div class="col-md-6"><strong>{{ __('Name') }}:</strong> {{ $author->name }}</div>
            <div class="col-12"><strong>{{ __('Description') }}:</strong> {{ $author->description ?? '—' }}</div>
        </div>
    </div>
</div>
@endsection
