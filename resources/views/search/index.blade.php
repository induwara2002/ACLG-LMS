@php
    $routePrefix = auth()->user()?->isMainAdmin() ? 'admin' : (auth()->user()?->hasRole('LIBRARIAN') ? 'librarian' : 'member');
@endphp

@extends('layouts.app')

@section('title', __('Global Search'))

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">{{ __('Global Search') }}</h1>
            <p class="page-subtitle">{{ __('Search books, authors, publishers, categories and operational records.') }}</p>
        </div>
        <a href="{{ route($routePrefix . '.dashboard') }}" class="btn btn-outline-secondary">{{ __('Dashboard') }}</a>
    </div>

    <form method="GET" action="{{ route($routePrefix . '.search') }}" class="row g-3 mb-4">
        @include('search._sabha-filter')
        <div class="col-md-10">
            <input type="text" name="q" class="form-control form-control-lg" value="{{ $query }}" placeholder="{{ __('Search books, authors, publishers, categories, members...') }}">
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-primary btn-lg">{{ __('Search') }}</button>
        </div>
    </form>

    @if ($query === '')
        <div class="alert alert-secondary">{{ __('Enter a search term to begin.') }}</div>
        @return
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">{{ __('Books') }}</div>
                <div class="card-body">
                    @forelse ($results['books'] as $book)
                        <div class="border-bottom pb-2 mb-2">
                            <strong>{{ $book->title }}</strong><br>
                            <small class="text-muted">{{ $book->book_id }} · {{ $book->isbn }} · {{ $book->language }}</small>
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ __('No matching books.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">{{ __('Authors') }}</div>
                <div class="card-body">
                    @forelse ($results['authors'] as $author)
                        <div class="border-bottom pb-2 mb-2">{{ $author->name }}</div>
                    @empty
                        <p class="text-muted mb-0">{{ __('No matching authors.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">{{ __('Publishers') }}</div>
                <div class="card-body">
                    @forelse ($results['publishers'] as $publisher)
                        <div class="border-bottom pb-2 mb-2">{{ $publisher->name }}</div>
                    @empty
                        <p class="text-muted mb-0">{{ __('No matching publishers.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">{{ __('Categories') }}</div>
                <div class="card-body">
                    @forelse ($results['categories'] as $category)
                        <div class="border-bottom pb-2 mb-2">{{ $category->code }} - {{ $category->name }}</div>
                    @empty
                        <p class="text-muted mb-0">{{ __('No matching categories.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @if ($canViewOperational)
        <div class="row g-4 mt-2">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">{{ __('Members') }}</div>
                    <div class="card-body">
                        @forelse ($results['members'] as $member)
                            <div class="border-bottom pb-2 mb-2">
                                <strong>{{ $member->full_name }}</strong><br>
                                <small class="text-muted">{{ $member->member_id }} · {{ $member->email }}</small>
                            </div>
                        @empty
                            <p class="text-muted mb-0">{{ __('No matching members.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">{{ __('Borrowings') }}</div>
                    <div class="card-body">
                        @forelse ($results['borrowings'] as $borrowing)
                            <div class="border-bottom pb-2 mb-2">
                                <strong>{{ $borrowing->id }}</strong> · {{ $borrowing->member->full_name ?? '—' }}<br>
                                <small class="text-muted">{{ $borrowing->book->title ?? '—' }} · {{ $borrowing->bookCopy->accession_number ?? '—' }}</small>
                            </div>
                        @empty
                            <p class="text-muted mb-0">{{ __('No matching borrowings.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">{{ __('Lost Books') }}</div>
                    <div class="card-body">
                        @forelse ($results['lostBooks'] as $lostBook)
                            <div class="border-bottom pb-2 mb-2">
                                <strong>{{ $lostBook->member->full_name ?? '—' }}</strong><br>
                                <small class="text-muted">{{ $lostBook->book->title ?? '—' }} · {{ $lostBook->bookCopy->accession_number ?? '—' }}</small>
                            </div>
                        @empty
                            <p class="text-muted mb-0">{{ __('No matching lost books.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">{{ __('Damaged Books') }}</div>
                    <div class="card-body">
                        @forelse ($results['damageRecords'] as $damage)
                            <div class="border-bottom pb-2 mb-2">
                                <strong>{{ $damage->member->full_name ?? '—' }}</strong><br>
                                <small class="text-muted">{{ $damage->book->title ?? '—' }} · {{ $damage->bookCopy->accession_number ?? '—' }}</small>
                            </div>
                        @empty
                            <p class="text-muted mb-0">{{ __('No matching damaged books.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
