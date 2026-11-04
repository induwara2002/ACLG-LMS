@extends('layouts.admin')

@section('title', __('Book Copies'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="page-title">{{ __('Book Copies') }}</h1><p class="page-subtitle">{{ __('Manage physical library copies') }}</p></div>
    @unless (auth()->user()->isMainAdmin())
        <a href="{{ route('admin.book-copies.create') }}" class="btn btn-primary">{{ __('Add') }} {{ __('Copy') }}</a>
    @endunless
</div>
<div class="card mb-4"><div class="card-body"><form method="GET" action="{{ route('admin.book-copies.index') }}" class="row g-3 align-items-end"><div class="col-md-10"><label class="form-label" for="copy-search">{{ __('Search') }} {{ __('copies') }}</label><input id="copy-search" type="search" name="search" class="form-control" value="{{ $search ?? '' }}" placeholder="{{ __('Accession number, title, or status') }}"></div><div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary">{{ __('Search') }}</button></div></form></div></div>
<div class="card"><div class="card-body p-0">@if ($bookCopies->count())<div class="table-responsive"><table class="table table-striped mb-0 align-middle"><thead><tr><th>{{ __('Accession Number') }}</th><th>{{ __('Book') }}</th><th>{{ __('Status') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Acquisition') }} {{ __('Date') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead><tbody>@foreach ($bookCopies as $bookCopy)<tr><td>{{ $bookCopy->accession_number }}</td><td>{{ $bookCopy->book->title ?? '—' }}</td><td>{{ __($bookCopy->status) }}</td><td>{{ __($bookCopy->condition) }}</td><td>{{ optional($bookCopy->acquisition_date)->format('Y-m-d') ?? '—' }}</td><td class="text-end"><div class="btn-group btn-group-sm"><a href="{{ route('admin.book-copies.show', $bookCopy) }}" class="btn btn-outline-info">{{ __('View') }}</a>@unless (auth()->user()->isMainAdmin())<a href="{{ route('admin.book-copies.edit', $bookCopy) }}" class="btn btn-outline-primary">{{ __('Edit') }}</a><form action="{{ route('admin.book-copies.destroy', $bookCopy) }}" method="POST" onsubmit="return confirm(@js(__('Are you sure you want to delete this record?')));">@csrf @method('DELETE')<button type="submit" class="btn btn-outline-danger">{{ __('Delete') }}</button></form>@endunless</div></td></tr>@endforeach</tbody></table></div><div class="p-3">{{ $bookCopies->links() }}</div>@else<div class="p-4 text-center text-muted">{{ __('No book copies found.') }}</div>@endif</div></div>
@endsection