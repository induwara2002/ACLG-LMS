@extends('layouts.app')

@section('title', __('Pradeshiya Sabha – Galle').' | '.__('Library Management System'))

@section('content')
@php
    $user = auth()->user();
    $routePrefix = $user?->hasRole('MEMBER')
        ? 'member'
        : ($user?->hasRole('LIBRARIAN') ? 'librarian' : 'admin');
    $catalogUrl = route('public.books.search');
    $circulationUrl = $user
        ? ($routePrefix === 'member' ? route('member.borrowings') : route($routePrefix . '.borrowings.index'))
        : route('login');
    $renewalUrl = $user
        ? ($routePrefix === 'member' ? route('member.borrowings') : route($routePrefix . '.renewals.index'))
        : route('login');
@endphp

<x-library-splash />

<div class="public-home">
    <header class="public-header">
        <div class="container public-header-inner">
            <a class="public-brand" href="{{ route('home') }}" aria-label="{{ __('Library home') }}">
                <img src="{{ asset('images/government-logo.png') }}" alt="" width="58" height="58">
                <span class="public-brand-copy">
                    <strong>{{ __('Pradeshiya Sabha – Galle') }}</strong>
                    <small>{{ __('Library Management System') }}</small>
                </span>
            </a>
            <x-language-switcher />

            <button class="public-nav-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavigation" aria-controls="publicNavigation" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                <span></span><span></span><span></span>
            </button>

            <nav class="collapse public-navigation" id="publicNavigation" aria-label="{{ __('Main navigation') }}">
                <a href="{{ route('home') }}">{{ __('Home') }}</a>
                <a href="#featured">{{ __('Books') }}</a>
                <a href="#about">{{ __('About') }}</a>
                <a href="#contact">{{ __('Contact') }}</a>
                <div class="public-nav-actions">
                    <a class="public-register-link" href="#membership">{{ __('Member Register') }}</a>
                    <a class="public-login-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="public-home-main">
        <section class="library-hero" aria-labelledby="hero-title">
            <div class="container library-hero-content">
                <p class="hero-eyebrow">{{ __('Pradeshiya Sabha – Galle') }}</p>
                <h1 id="hero-title">{{ __('A place to discover') }}<br>{{ __('your next great read.') }}</h1>
                <p class="hero-description">{{ __('Explore knowledge, stories and ideas at your community library.') }}</p>

                <form class="catalog-search" method="GET" action="{{ $catalogUrl }}" role="search">
                    <label class="visually-hidden" for="catalog-search-input">{{ __('Search the library catalog') }}</label>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 4.4 4.4"></path></svg>
                    <input id="catalog-search-input" type="search" name="search" value="{{ old('search') }}" maxlength="255" placeholder="{{ __('Search title, author, ISBN, accession number or category') }}" required>
                    <button type="submit">{{ __('Search books') }}</button>
                </form>
                <p class="hero-note">{{ __('Find a book, manage your membership, and keep learning.') }}</p>
            </div>
        </section>

        <section class="quick-actions-section" aria-label="{{ __('Library services') }}">
            <div class="container">
                <div class="quick-actions-grid">
                    <a class="quick-action-card" href="{{ $catalogUrl }}">
                        <span class="action-icon action-icon-green" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21V5.5Z"></path><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20M8 7h8M8 10h8"></path></svg>
                        </span>
                        <span><strong>{{ __('Browse Books') }}</strong><small>{{ __('Explore the library catalog') }}</small></span>
                        <span class="action-arrow" aria-hidden="true">→</span>
                    </a>
                    <a class="quick-action-card" href="#membership">
                        <span class="action-icon action-icon-gold" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20a6.5 6.5 0 0 1 13 0M17 8h5m-2.5-2.5v5"></path></svg>
                        </span>
                        <span><strong>{{ __('Member Registration') }}</strong><small>{{ __('Learn how to join the library') }}</small></span>
                        <span class="action-arrow" aria-hidden="true">→</span>
                    </a>
                    <a class="quick-action-card" href="{{ $circulationUrl }}">
                        <span class="action-icon action-icon-blue" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M4 7h14l-2.5-2.5M20 17H6l2.5 2.5"></path><path d="M18 7a7 7 0 0 1 1.2 8M6 17a7 7 0 0 1-1.2-8"></path></svg>
                        </span>
                        <span><strong>{{ __('Issue / Return') }}</strong><small>{{ __('Manage your borrowing activity') }}</small></span>
                        <span class="action-arrow" aria-hidden="true">→</span>
                    </a>
                    <a class="quick-action-card" href="{{ $renewalUrl }}">
                        <span class="action-icon action-icon-plum" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M20 7v5h-5M4 17v-5h5"></path><path d="M5.6 9A7 7 0 0 1 18 6.5L20 12M4 12l2 5.5A7 7 0 0 0 18.4 15"></path></svg>
                        </span>
                        <span><strong>{{ __('Renewal') }}</strong><small>{{ __('Check your borrowing options') }}</small></span>
                        <span class="action-arrow" aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </section>

        <section class="home-section featured-section" id="featured" aria-labelledby="featured-title">
            <div class="container">
                <div class="section-heading">
                    <div>
                        <p class="section-kicker">{{ __('Explore the collection') }}</p>
                        <h2 id="featured-title">{{ __('Featured Books') }}</h2>
                    </div>
                    <a class="text-link" href="{{ $catalogUrl }}">{{ __('Browse catalog') }} <span aria-hidden="true">→</span></a>
                </div>

                @if (isset($featuredBooks) && $featuredBooks->isNotEmpty())
                    <div class="featured-books-grid">
                        @foreach ($featuredBooks as $book)
                            <a class="featured-book-card" href="{{ route('public.books.show', $book) }}">
                                <div class="book-cover-placeholder" aria-hidden="true">
                                    <span>{{ \Illuminate\Support\Str::limit($book->title, 34) }}</span>
                                </div>
                                <div class="featured-book-info">
                                    <h3>{{ $book->title }}</h3>
                                    <p>{{ $book->author?->name ?? __('Library collection') }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="catalog-empty-state">
                        <span class="empty-book-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21V5.5Z"></path><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20"></path></svg>
                        </span>
                        <div>
                            <h3>{{ __('Discover our collection') }}</h3>
                            <p>{{ __('Search the library catalogue to find a title, author or subject.') }}</p>
                        </div>
                        <a class="text-link" href="{{ $catalogUrl }}">{{ __('Browse books') }} <span aria-hidden="true">→</span></a>
                    </div>
                @endif
            </div>
        </section>

        <section class="library-glance-section" aria-labelledby="glance-title">
            <div class="container">
                <div class="glance-intro">
                    <p class="section-kicker">{{ __('Serving our community') }}</p>
                    <h2 id="glance-title">{{ __('Library at a glance') }}</h2>
                    <p>{{ __('Our library connects readers with books and helpful library services.') }}</p>
                </div>
                <div class="glance-grid">
                    <div class="glance-item">
                        <span class="glance-value">{{ number_format($bookCount) }}</span>
                        <strong>{{ __('Books in the collection') }}</strong>
                        <small>{{ __('Book titles currently listed in the library catalogue.') }}</small>
                    </div>
                    <div class="glance-item">
                        <span class="glance-value">—</span>
                        <strong>{{ __('Library members') }}</strong>
                        <small>{{ __('Member information is protected within the library system.') }}</small>
                    </div>
                    <div class="glance-item">
                        <span class="glance-value">—</span>
                        <strong>{{ __('Borrowing activity') }}</strong>
                        <small>{{ __('Sign in to view your own borrowing and renewal details.') }}</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="home-section about-section" id="about" aria-labelledby="about-title">
            <div class="container about-layout">
                <div class="about-emblem" aria-label="{{ __('Official government emblem') }}">
                    <img src="{{ asset('images/government-logo.png') }}" alt="">
                    <span>{{ __('Pradeshiya Sabha') }}<br>Galle</span>
                </div>
                <div class="about-copy">
                    <p class="section-kicker">{{ __('A welcoming place to learn') }}</p>
                    <h2 id="about-title">{{ __('Our Library') }}</h2>
                    <p>{{ __('The library is a community space for reading, learning and discovery. The library management system helps members and library staff access available services.') }}</p>
                    <div class="membership-note" id="membership">
                        <strong>{{ __('Interested in becoming a member?') }}</strong>
                        <p>{{ __('Online member registration is not available through this portal. Please contact or visit the library for membership information.') }}</p>
                    </div>
                    <a class="public-outline-link" href="{{ route('login') }}">{{ __('Member Login') }}</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="public-footer" id="contact">
        <div class="container public-footer-main">
            <div class="footer-brand">
                <img src="{{ asset('images/sri-lanka-state-emblem.png') }}" alt="Sri Lankan State Emblem" width="56" height="56">
                <div>
                    <strong lang="si">{{ config('library.footer_office_name', 'ප්‍රාදේශීය ලේකම් කාර්යාලය') }}</strong>
                    <p lang="si">පුස්තකාල කළමනාකරණ පද්ධතිය</p>
                </div>
            </div>
            <div class="footer-contact">
                <h2>{{ __('Contact') }}</h2>
                <p>{{ __('For library services and membership information, please contact the office.') }} {{ config('library.footer_office_name', 'ප්‍රාදේශීය ලේකම් කාර්යාලය') }}</p>
                <span>{{ __('Galle, Southern Province, Sri Lanka') }}</span>
            </div>
            <div class="footer-links">
                <h2>{{ __('Quick links') }}</h2>
                <a href="{{ route('home') }}">{{ __('Home') }}</a>
                <a href="#featured">{{ __('Books') }}</a>
                <a href="#about">{{ __('About the library') }}</a>
                <a href="{{ route('login') }}">{{ __('Login') }}</a>
            </div>
        </div>
        <div class="public-footer-bottom">
            <div class="container">
                <span>© {{ date('Y') }} {{ config('library.footer_office_name', 'ප්‍රාදේශීය ලේකම් කාර්යාලය') }} · පුස්තකාල කළමනාකරණ පද්ධතිය</span>
                <span>{{ __('Serving our community through knowledge and reading.') }}</span>
            </div>
        </div>
    </footer>
</div>
@endsection
