@extends('layouts.admin')

@section('title', __('Add Category'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ __('Add Category') }}</h1>
        <p class="page-subtitle">{{ __('Create a DDC category') }}</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.categories.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="parent_id">{{ __('Main DDC range *') }}</label>
                    <select id="parent_id" name="parent_id" class="form-select @error('parent_id') is-invalid @enderror" required>
                        <option value="">{{ __('Select a range') }}</option>
                        @foreach ($rangeCategories as $range)
                            <option value="{{ $range->id }}" @selected(old('parent_id') == $range->id)>{{ $range->code }} · {{ \App\Models\Category::MAIN_DDC_RANGES[$range->code] }} · {{ $range->name }}</option>
                        @endforeach
                    </select>
                    @error('parent_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="code">{{ __('DDC Number *') }}</label>
                    <input id="code" type="text" name="code" inputmode="decimal" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" required maxlength="10">
                    @error('code') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="name">{{ __('Category name *') }}</label>
                    <input id="name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required maxlength="255">
                    @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('Description') }}</label>
                    <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                    @error('description') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('Save Category') }}</button>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
