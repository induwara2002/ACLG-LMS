@extends('layouts.admin')

@section('title', __('Category Details'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ __('Category Details') }}</h1>
        <p class="page-subtitle">{{ __('DDC classification details') }}</p>
    </div>
    <div class="btn-group">
        @unless (auth()->user()->isMainAdmin())
            <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-primary">{{ __('Edit') }}</a>
        @endunless
        <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><strong>{{ __('Code') }}:</strong> {{ $category->code }}</div>
            <div class="col-md-8"><strong>{{ __('Name') }}:</strong> {{ $category->name }}</div>
            <div class="col-12"><strong>{{ __('Description') }}:</strong> {{ $category->description ?? '—' }}</div>
        </div>
    </div>
</div>
@endsection
