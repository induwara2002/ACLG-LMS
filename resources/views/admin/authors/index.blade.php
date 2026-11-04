@extends('layouts.admin')

@section('title', __('Authors'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ __('Authors') }}</h1>
        <p class="page-subtitle">{{ __('Manage library authors') }}</p>
    </div>
    @unless (auth()->user()->isMainAdmin())
        <a href="{{ route('admin.authors.create') }}" class="btn btn-primary">{{ __('Add Author') }}</a>
    @endunless
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.authors.index') }}" class="row g-3 align-items-end">
            <div class="col-md-10">
                <label class="form-label">{{ __('Search') }}</label>
                <input type="text" name="search" class="form-control" value="{{ $search ?? '' }}" placeholder="{{ __('Search by author name') }}">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">{{ __('Search') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        @if ($authors->count())
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('common.id') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Number of Books') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($authors as $author)
                            <tr>
                                <td>{{ $author->id }}</td>
                                <td>{{ $author->name }}</td>
                                <td>{{ $author->books_count }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.authors.show', $author) }}" class="btn btn-outline-info">{{ __('View') }}</a>
                                        @unless (auth()->user()->isMainAdmin())
                                            <a href="{{ route('admin.authors.edit', $author) }}" class="btn btn-outline-primary">{{ __('Edit') }}</a>
                                            <form action="{{ route('admin.authors.destroy', $author) }}" method="POST" onsubmit="return confirm(@js(__('Are you sure you want to delete this record?')));">
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
            <div class="p-3">{{ $authors->links() }}</div>
        @else
            <div class="p-4 text-center text-muted">{{ __('No authors found.') }}</div>
        @endif
    </div>
</div>
@endsection
