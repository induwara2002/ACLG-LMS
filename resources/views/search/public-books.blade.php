@extends('layouts.app')

@section('title', __('Search the Library Catalogue'))

@section('content')
<main class="public-search-page">
    <div class="container py-5">
        <a class="public-search-back" href="{{ route('home') }}">
            <span class="public-search-back-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><path d="M19 12H5m7 7-7-7 7-7"></path></svg>
            </span>
            <span>{{ __('Pradeshiya Sabha – Galle') }} {{ __('Library') }}</span>
        </a>
        <div class="public-search-heading">
            <p class="section-kicker">{{ __('Library catalogue') }}</p>
            <h1>{{ __('Search books') }}</h1>
            <p>{{ __('Find books by title, author, ISBN, accession number, publisher or category.') }}</p>
        </div>

        <form class="public-results-search" method="GET" action="{{ route('public.books.search') }}" role="search">
            <label class="visually-hidden" for="public-book-search">{{ __('Search catalogue') }}</label>
            <input id="public-book-search" type="search" name="search" value="{{ $query }}" maxlength="255" required>
            <button class="btn btn-primary" type="submit">{{ __('Search catalogue') }}</button>
        </form>
        @error('search')
            <p class="text-danger mt-2" role="alert">{{ $message }}</p>
        @enderror

        <div class="public-results-summary">
            <h2>@if ($query !== '') {{ __('Results for :search', ['search' => $query]) }} @else {{ __('Books in the collection') }} @endif</h2>
            <span>{{ $books->total() }} {{ $books->total() === 1 ? __('Book') : __('Books') }}</span>
        </div>

        @if ($books->isNotEmpty())
            <div class="public-results-grid">
                @foreach ($books as $book)
                    <a class="public-result-card" href="{{ route('public.books.show', ['book' => $book, 'search' => $query]) }}">
                        <img class="public-result-cover" src="{{ $book->cover_image_url }}" alt="{{ __('Cover image for :title', ['title' => $book->title]) }}">
                        <span class="public-result-copy">
                            <strong>{{ $book->title }}</strong>
                            <span>{{ $book->author?->name ?? __('Author not listed') }}</span>
                            <small>{{ $book->publisher?->name ?? __('Publisher not listed') }} · {{ $book->category?->name ?? __('Uncategorised') }}</small>
                            <small>{{ __('ISBN') }} {{ $book->isbn ?: __('not listed') }}</small>
                            <span class="public-result-availability">{{ __('Catalogue record') }}</span>
                        </span>
                        <span class="public-result-arrow" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
            <div class="mt-4">{{ $books->links() }}</div>
        @else
            <div class="public-search-empty">
                <span aria-hidden="true">⌕</span>
                <h2>{{ __('No books found') }}</h2>
                <p>{{ __('Try another title, author, ISBN, accession number, publisher or category.') }}</p>
                <a class="btn btn-outline-primary" href="{{ route('home') }}">{{ __('Return to the library home page') }}</a>
            </div>
        @endif
    </div>
</main>
@endsection
