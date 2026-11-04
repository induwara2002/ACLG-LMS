@php
    $routePrefix = auth()->user()?->isMainAdmin() ? 'admin' : (auth()->user()?->hasRole('LIBRARIAN') ? 'librarian' : 'member');
@endphp

@extends($isMemberView ? 'layouts.member' : 'layouts.app')

@section('title', __('Book Search'))

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">{{ __('Book') }} {{ __('Search') }}</h1>
            <p class="page-subtitle">{{ __('Advanced catalog filtering and database-driven search.') }}</p>
        </div>
        <a href="{{ route($routePrefix . '.dashboard') }}" class="btn btn-outline-secondary">{{ __('Dashboard') }}</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route($routePrefix . '.books.search') }}" class="row g-3 align-items-end">
                @include('search._sabha-filter')
                <div class="col-md-4">
                    <label class="form-label">{{ __('Search') }}</label>
                    <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ $isMemberView ? __('Title, ISBN, author or category...') : __('Title, ISBN, book ID, author, category...') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('Author') }}</label>
                    <select name="author_id" class="form-select">
                        <option value="">{{ __('All authors') }}</option>
                        @foreach ($authors as $author)
                            <option value="{{ $author->id }}" @selected(($filters['author_id'] ?? null) == $author->id)>{{ $author->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('Publisher') }}</label>
                    <select name="publisher_id" class="form-select">
                        <option value="">{{ __('All publishers') }}</option>
                        @foreach ($publishers as $publisher)
                            <option value="{{ $publisher->id }}" @selected(($filters['publisher_id'] ?? null) == $publisher->id)>{{ $publisher->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
                </div>

                <div class="col-md-3">
                    <label class="form-label">{{ __('Category') }}</label>
                    <select name="category_id" class="form-select">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) == $category->id)>{{ $category->code }} - {{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('DDC') }}</label>
                    <input type="text" name="ddc" class="form-control" value="{{ $filters['ddc'] ?? '' }}" placeholder="{{ __('600, 600.25') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Language') }}</label>
                    <select name="language" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($languages as $language)
                            <option value="{{ $language }}" @selected(($filters['language'] ?? null) == $language)>{{ $language }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Year') }}</label>
                    <input type="number" name="publication_year" class="form-control" value="{{ $filters['publication_year'] ?? '' }}" min="1900" max="2100">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('From') }} / {{ __('To') }}</label>
                    <div class="input-group">
                        <input type="number" name="publication_year_from" class="form-control" value="{{ $filters['publication_year_from'] ?? '' }}" min="1900" max="2100" placeholder="{{ __('From') }}">
                        <input type="number" name="publication_year_to" class="form-control" value="{{ $filters['publication_year_to'] ?? '' }}" min="1900" max="2100" placeholder="{{ __('To') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Availability') }}</label>
                    <select name="availability" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($isMemberView ? ['AVAILABLE'] : ['AVAILABLE', 'ISSUED', 'OVERDUE', 'LOST', 'DAMAGED', 'REPAIR'] as $status)
                            <option value="{{ $status }}" @selected(($filters['availability'] ?? null) == $status)>{{ __($status) }}</option>
                        @endforeach
                    </select>
                </div>
                @unless ($isMemberView)
                <div class="col-md-2">
                    <label class="form-label">{{ __('Shelf') }}</label>
                    <input type="text" name="shelf_number" class="form-control" value="{{ $filters['shelf_number'] ?? '' }}" placeholder="{{ __('Shelf number, e.g. A1') }}">
                </div>
                @endunless
                <div class="col-md-2">
                    <label class="form-label">{{ __('Sort') }}</label>
                    <select name="sort" class="form-select">
                        <option value="title" @selected(($filters['sort'] ?? 'title') == 'title')>{{ __('Title') }}</option>
                        <option value="publication_year" @selected(($filters['sort'] ?? 'title') == 'publication_year')>{{ __('Publication year') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Direction') }}</label>
                    <select name="direction" class="form-select">
                        <option value="asc" @selected(($filters['direction'] ?? 'asc') == 'asc')>{{ __('Ascending') }}</option>
                        <option value="desc" @selected(($filters['direction'] ?? 'asc') == 'desc')>{{ __('Descending') }}</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <a href="{{ route($routePrefix . '.books.search') }}" class="btn btn-outline-secondary">{{ __('Clear') }}</a>
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
                                @unless ($isMemberView)<th>{{ __('Book') }} ID</th>@endunless
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('ISBN') }}</th>
                                <th>{{ __('Author') }}</th>
                                <th>{{ __('Publisher') }}</th>
                                <th>{{ __('Category') }}</th>
                                <th>{{ __('DDC') }}</th>
                                <th>{{ __('Language') }}</th>
                                <th>{{ __('Year') }}</th>
                                <th>{{ __('Edition') }}</th>
                                <th>{{ __('Available') }}</th>
                                @unless ($isMemberView)<th>{{ __('Shelf') }}</th>@endunless
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($books as $book)
                                <tr>
                                    @unless ($isMemberView)<td>{{ $book->book_id }}</td>@endunless
                                    <td>@if ($isMemberView)<a href="{{ route('member.books.show', $book) }}">{{ $book->title }}</a>@else{{ $book->title }}@endif</td>
                                    <td>{{ $book->isbn }}</td>
                                    <td>{{ $book->author->name ?? '—' }}</td>
                                    <td>{{ $book->publisher->name ?? '—' }}</td>
                                    <td>{{ $book->category->name ?? '—' }}</td>
                                    <td>{{ $book->ddc_number }}</td>
                                    <td>{{ $book->language }}</td>
                                    <td>{{ $book->publication_year }}</td>
                                    <td>{{ $book->edition ?? '—' }}</td>
                                    <td>{{ $isMemberView ? ($book->inventories->sum('available_quantity') > 0 ? __('Available') : __('Currently unavailable')) : $book->copies->where('status', 'AVAILABLE')->count().'/'.$book->copies->count() }}</td>
                                    @unless ($isMemberView)<td>{{ $book->shelf_number ?? '—' }}</td>@endunless
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $books->links() }}</div>
            @else
                <div class="p-4 text-center text-muted">{{ __('No books match the selected filters.') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
