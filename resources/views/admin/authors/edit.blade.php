@extends('layouts.admin')

@section('title', __('Edit Author'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ __('Edit Author') }}</h1>
        <p class="page-subtitle">{{ __('Update author details') }}</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.authors.update', $author) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="author-name">{{ __('Full name') }}</label>
                    <input id="author-name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $author->name) }}" maxlength="255" required>
                    @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Description') }}</label>
                    <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $author->description) }}</textarea>
                    @error('description') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('Update Author') }}</button>
                <a href="{{ route('admin.authors.show', $author) }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
