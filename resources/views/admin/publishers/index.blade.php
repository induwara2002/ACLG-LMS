@extends('layouts.admin')

@section('title', __('Publishers'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ __('Publishers') }}</h1>
        <p class="page-subtitle">{{ __('Manage library publishers') }}</p>
    </div>
    @unless (auth()->user()->isMainAdmin())
        <a href="{{ route('admin.publishers.create') }}" class="btn btn-primary">{{ __('Add Publisher') }}</a>
    @endunless
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.publishers.index') }}" class="row g-3 align-items-end">
            <div class="col-md-10">
                <label class="form-label">{{ __('Search') }}</label>
                <input type="text" name="search" class="form-control" value="{{ $search ?? '' }}" placeholder="{{ __('Search by publisher name') }}">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">{{ __('Search') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        @if ($publishers->count())
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('common.id') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($publishers as $publisher)
                            <tr>
                                <td>{{ $publisher->id }}</td>
                                <td>{{ $publisher->name }}</td>
                                <td>{{ $publisher->phone ?? '—' }}</td>
                                <td>{{ $publisher->email ?? '—' }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.publishers.show', $publisher) }}" class="btn btn-outline-info">{{ __('View') }}</a>
                                        @unless (auth()->user()->isMainAdmin())
                                            <a href="{{ route('admin.publishers.edit', $publisher) }}" class="btn btn-outline-primary">{{ __('Edit') }}</a>
                                            <form action="{{ route('admin.publishers.destroy', $publisher) }}" method="POST" onsubmit="return confirm(@js(__('Are you sure you want to delete this record?')));">
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
            <div class="p-3">{{ $publishers->links() }}</div>
        @else
            <div class="p-4 text-center text-muted">{{ __('No publishers found.') }}</div>
        @endif
    </div>
</div>
@endsection
