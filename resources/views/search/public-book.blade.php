@extends('layouts.app')

@section('title', $book->title)

@section('content')
<main class="public-search-page">
    <div class="container py-5">
        @if (request()->filled('search'))
            <a class="public-search-back" href="{{ route('public.books.search', ['search' => request()->query('search')]) }}">
                <span class="public-search-back-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false"><path d="M19 12H5m7 7-7-7 7-7"></path></svg>
                </span>
                <span>{{ __('Back to search results') }}</span>
            </a>
        @else
            <a class="public-search-back" href="{{ route('home') }}">
                <span class="public-search-back-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false"><path d="M19 12H5m7 7-7-7 7-7"></path></svg>
                </span>
                <span>{{ __('Return to the library home page') }}</span>
            </a>
        @endif
        <article class="public-book-detail">
            <img class="public-book-detail-cover" src="{{ $book->cover_image_url }}" alt="{{ __('Cover image for :title', ['title' => $book->title]) }}">
            <div class="public-book-detail-copy">
                <p class="section-kicker">{{ __('Library catalogue record') }}</p>
                <h1>{{ $book->title }}</h1>
                <p class="public-book-author">{{ $book->author?->name ?? 'Author not listed' }}</p>
                <dl class="public-book-metadata">
                    <div><dt>{{ __('ISBN') }}</dt><dd>{{ $book->isbn ?: '—' }}</dd></div>
                    <div><dt>{{ __('Publisher') }}</dt><dd>{{ $book->publisher?->name ?? '—' }}</dd></div>
                    <div><dt>{{ __('Category') }}</dt><dd>{{ $book->category?->name ?? '—' }}</dd></div>
                    <div><dt>{{ __('Publication year') }}</dt><dd>{{ $book->publication_year ?: '—' }}</dd></div>
                    <div><dt>{{ __('Edition') }}</dt><dd>{{ $book->edition ?: '—' }}</dd></div>
                    <div><dt>{{ __('Availability') }}</dt><dd>{{ __('Contact your library for local availability.') }}</dd></div>
                </dl>
                @if ($book->description)
                    <p class="public-book-description">{{ $book->description }}</p>
                @endif
                <a class="btn btn-primary" href="{{ route('public.books.search', ['search' => $book->title]) }}">{{ __('Find this book in the catalogue') }}</a>
            </div>
        </article>
    </div>
</main>
@endsection
