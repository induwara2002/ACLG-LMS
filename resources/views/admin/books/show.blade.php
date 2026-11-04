@extends('layouts.admin')

@section('title', __('Book Details'))

@section('content')
@php($routePrefix = request()->routeIs('librarian.*') ? 'librarian' : 'admin')
@php($availableCopies = $book->copies->where('status', 'AVAILABLE')->count())
<header class="book-catalogue-header report-page-header">
    <div><p class="section-kicker">{{ __('Library catalogue') }}</p><h1 class="page-title">{{ $book->title }}</h1><p class="page-subtitle">{{ __('Book record') }} {{ $book->book_id }}</p></div>
    <div class="d-flex flex-wrap gap-2">@unless (auth()->user()->isMainAdmin())<a href="{{ route($routePrefix . '.books.edit', $book) }}" class="btn btn-primary">{{ __('Edit book') }}</a>@endunless<a href="{{ route($routePrefix . '.books.index') }}" class="btn btn-outline-secondary">{{ __('Back to books') }}</a></div>
</header>

<section class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <img src="{{ $book->cover_image_url }}" alt="{{ __('Cover image for :title', ['title' => $book->title]) }}" class="book-cover-detail">
            <div><div class="text-muted small">{{ __('Availability') }}</div><span class="badge text-bg-{{ $availableCopies > 0 ? 'success' : 'secondary' }}">{{ __($availableCopies > 0 ? 'Available' : 'No copies available') }}</span></div>
            <div class="text-md-end"><div class="text-muted small">{{ __('Physical copies') }}</div><strong>{{ $availableCopies }} {{ __('available') }} / {{ $book->copies->count() }} {{ __('total') }}</strong></div>
        </div>
        <dl class="row g-3 mb-0">
            <div class="col-md-6"><dt>{{ __('Author') }}</dt><dd>{{ $book->author?->name ?? '—' }}</dd></div>
            <div class="col-md-6"><dt>{{ __('Publisher') }}</dt><dd>{{ $book->publisher?->name ?? '—' }}</dd></div>
            <div class="col-md-4"><dt>{{ __('ISBN') }}</dt><dd>{{ $book->isbn ?? '—' }}</dd></div>
            <div class="col-md-4"><dt>{{ __('Category') }}</dt><dd>{{ $book->category ? $book->category->code . ' - ' . $book->category->name : '—' }}</dd></div>
            <div class="col-md-4"><dt>{{ __('Accession / book ID') }}</dt><dd>{{ $book->book_id }}</dd></div>
            <div class="col-md-4"><dt>{{ __('Shelf location') }}</dt><dd>{{ $book->shelf_number ?? '—' }}</dd></div>
            <div class="col-md-4"><dt>{{ __('Publication year') }}</dt><dd>{{ $book->publication_year ?? '—' }}</dd></div>
            <div class="col-md-4"><dt>{{ __('Edition') }}</dt><dd>{{ $book->edition ?? '—' }}</dd></div>
            <div class="col-12"><dt>{{ __('Description') }}</dt><dd>{{ $book->description ?? '—' }}</dd></div>
        </dl>
    </div>
</section>

<section class="card">
    <div class="card-header bg-white"><h2 class="h5 mb-0">{{ __('Physical copies and circulation') }}</h2></div>
    <div class="card-body p-0">
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>{{ __('Accession number') }}</th><th>{{ __('Status') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Acquired') }}</th><th>{{ __('Current borrower') }}</th><th>{{ __('Due date') }}</th></tr></thead><tbody>
            @forelse ($book->copies as $copy)
                @php($currentBorrowing = $copy->borrowings->first())
                <tr><td>{{ $copy->accession_number }}</td><td><span class="status-badge">{{ __($copy->status) }}</span></td><td>{{ __($copy->condition) }}</td><td>{{ $copy->acquisition_date ?? '—' }}</td><td>{{ $currentBorrowing?->member?->full_name ?? '—' }}</td><td>{{ $currentBorrowing?->due_date?->format('Y-m-d') ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No physical copies have been recorded.') }}</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
</section>
@endsection
