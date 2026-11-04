@extends('layouts.admin')

@section('title', __('Books'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="page-title">{{ __('Books') }}</h1>
        <p class="page-subtitle">{{ __('Manage the collection, bibliographic records and physical copies.') }}</p>
    </div>
    @unless (auth()->user()->isMainAdmin())
        <a href="{{ route('admin.books.create') }}" class="btn btn-primary">{{ __('Add Book') }}</a>
    @endunless
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.books.index') }}" class="row g-3 align-items-end">
            <div class="col-md-9 col-xl-10">
                <label class="form-label" for="book-search">{{ __('Search') }}</label>
                <input id="book-search" type="search" name="search" class="form-control" value="{{ $search ?? '' }}" placeholder="{{ __('Title, book ID, ISBN, author, publisher or category') }}">
            </div>
            <div class="col-md-3 col-xl-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">{{ __('Search') }}</button>
            </div>
            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <a href="{{ route('admin.books.search') }}">{{ __('Advanced catalogue filters') }}</a>
                @unless (auth()->user()->isMainAdmin())
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.book-copies.index') }}">{{ __('Manage physical copies') }}</a>
                @endunless
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        @if ($books->count())
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('Cover') }}</th>
                            <th>{{ __('Acquisition Number') }}</th>
                            <th>{{ __('Book Name') }}</th>
                            <th>{{ __('ISBN') }}</th>
                            <th>{{ __('Author') }}</th>
                            <th>{{ __('Publisher') }}</th>
                            <th>{{ __('Category') }}</th>
                            <th>{{ __('Published Year') }}</th>
                            <th>{{ __('Edition') }}</th>
                            <th>{{ __('Copies') }}</th>
                            <th>{{ __('Available') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($books as $book)
                            <tr>
                                <td><img src="{{ $book->cover_image_url }}" alt="{{ __('Cover image for :title', ['title' => $book->title]) }}" width="48" height="64" class="book-cover-thumbnail"></td>
                                <td>{{ $book->book_id }}</td>
                                <td>{{ $book->title }}</td>
                                <td>{{ $book->isbn ?? '—' }}</td>
                                <td>{{ $book->author->name ?? '—' }}</td>
                                <td>{{ $book->publisher->name ?? '—' }}</td>
                                <td>{{ $book->category ? $book->category->code.' - '.$book->category->name : '—' }}</td>
                                <td>{{ $book->publication_year ?? '—' }}</td>
                                <td>{{ $book->edition ?? '—' }}</td>
                                <td>{{ $book->copies_count }}</td>
                                <td>{{ $book->available_copies_count }}</td>
                                <td><span class="badge {{ $book->available_copies_count > 0 ? 'text-bg-success' : 'text-bg-secondary' }}">{{ __($book->available_copies_count > 0 ? 'AVAILABLE' : 'UNAVAILABLE') }}</span></td>
                                <td class="text-end">
                                    <div class="table-row-actions">
                                        <a href="{{ route('admin.books.show', $book) }}" class="btn btn-outline-info">{{ __('View') }}</a>
                                        @unless (auth()->user()->isMainAdmin())
                                            <a href="{{ route('admin.books.edit', $book) }}" class="btn btn-outline-primary">{{ __('Edit') }}</a>
                                            <form action="{{ route('admin.books.destroy', $book) }}" method="POST" data-confirm="{{ __('Are you sure you want to delete this book record?') }}">
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
            <div class="p-3">{{ $books->links() }}</div>
        @else
            <div class="p-4 text-center text-muted">{{ __('No books found.') }}</div>
        @endif
    </div>
</div>
@endsection
