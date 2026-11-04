@extends('layouts.admin')

@section('title', __('Categories'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ __('Categories') }}</h1>
        <p class="page-subtitle">{{ __('Manage DDC categories') }}</p>
    </div>
    @unless (auth()->user()->isMainAdmin())
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">{{ __('Add Category') }}</a>
    @endunless
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.categories.index') }}" class="row g-3 align-items-end">
            <div class="col-md-10">
                <label class="form-label">{{ __('Search') }}</label>
                <input type="text" name="search" class="form-control" value="{{ $search ?? '' }}" placeholder="{{ __('Search by code or name') }}">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">{{ __('Search') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        @if ($categories->count())
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td>{{ $category->code }}</td>
                                <td>{{ $category->name }}</td>
                                <td>{{ $category->description ?? '—' }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.categories.show', $category) }}" class="btn btn-outline-info">{{ __('View') }}</a>
                                        @unless (auth()->user()->isMainAdmin())
                                            <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-outline-primary">{{ __('Edit') }}</a>
                                            <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" onsubmit="return confirm(@js(__('Are you sure you want to delete this record?')));">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger">{{ __('Delete') }}</button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $categories->links() }}</div>
        @else
            <div class="p-4 text-center text-muted">{{ __('No categories found.') }}</div>
        @endif
    </div>
</div>
@endsection
