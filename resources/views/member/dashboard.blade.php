@extends('layouts.member')

@section('title', __('dashboard.member'))

@section('content')
<div class="member-dashboard">
    <header class="member-welcome-panel">
        <div>
            <p class="member-eyebrow">{{ __('dashboard.member_portal') }}</p>
            <h1>{{ __('dashboard.welcome') }}, {{ $member->full_name }}</h1>
            <p>{{ __('dashboard.membership_overview') }}</p>
            <div class="member-welcome-meta">
                <span><strong>{{ __('dashboard.member_id') }}:</strong> {{ $member->member_id }}</span>
                <span><strong>{{ __('dashboard.registered_sabha') }}:</strong> {{ $member->pradeshiyaSabha?->name ?? __('dashboard.sabha_not_assigned') }}</span>
            </div>
        </div>
        <div class="member-status-panel">
            <span>{{ __('dashboard.membership_status') }}</span>
            <strong class="member-status-badge member-status-{{ strtolower($membershipStatus) }}">{{ __($membershipStatus) }}</strong>
        </div>
    </header>

    @if ($membershipStatus === 'PENDING')
        <div class="member-approval-message member-approval-pending" role="status">{{ __('dashboard.membership_pending_message') }}</div>
    @elseif ($membershipStatus === 'SUSPENDED')
        <div class="member-approval-message member-approval-suspended" role="status">{{ __('dashboard.membership_suspended_message') }}</div>
    @elseif ($membershipStatus === 'EXPIRED')
        <div class="member-approval-message member-approval-pending" role="status">{{ __('dashboard.membership_expired_message') }}</div>
    @elseif ($membershipStatus === 'REJECTED')
        <div class="member-approval-message member-approval-rejected" role="status">
            <strong>{{ __('dashboard.membership_rejected_message') }}</strong>
            @if ($member->rejection_reason)<p class="mb-0 mt-2">{{ $member->rejection_reason }}</p>@endif
            <p class="mb-0 mt-2">{{ __('dashboard.membership_rejected_contact', ['sabha' => $member->pradeshiyaSabha?->name ?? __('dashboard.library')]) }}</p>
        </div>
    @else
        <div class="member-approval-message member-approval-active" role="status">{{ __('dashboard.membership_active_message') }}</div>
    @endif
    @if (in_array($membershipStatus, ['PENDING', 'REJECTED'], true))
        <p class="member-application-date">{{ __('dashboard.application_submitted') }}: {{ $member->registration_date?->format('Y-m-d') ?? '—' }}</p>
    @endif

    <section class="member-summary-grid" aria-label="{{ __('dashboard.membership_summary') }}">
        <a class="member-summary-card" href="{{ route('member.borrowings') }}">
            <span class="member-summary-icon" aria-hidden="true">↗</span>
            <span>{{ __('dashboard.currently_borrowed') }}</span>
            <strong>{{ $activeBorrowingCount }}</strong>
            <small>{{ __('dashboard.books_on_loan') }}</small>
            <small>{{ __('Remaining allowance') }}: {{ $remainingAllowance }}</small>
        </a>
        <a class="member-summary-card" href="{{ route('member.borrowings') }}">
            <span class="member-summary-icon" aria-hidden="true">◷</span>
            <span>{{ __('dashboard.books_due_soon') }}</span>
            <strong>{{ $dueSoonBorrowings->count() }}</strong>
            <small>{{ __('dashboard.due_within_seven_days') }}</small>
        </a>
        <a class="member-summary-card" href="{{ route('member.dashboard') }}#reservations">
            <span class="member-summary-icon" aria-hidden="true">▤</span>
            <span>{{ __('dashboard.active_reservations') }}</span>
            <strong>{{ $activeReservationCount }}</strong>
            <small>{{ __('dashboard.pending_reservations') }}</small>
        </a>
        <a class="member-summary-card" href="{{ route('member.membership') }}">
            <span class="member-summary-icon" aria-hidden="true">✓</span>
            <span>{{ __('dashboard.membership_status') }}</span>
            <strong class="member-summary-status">{{ __($membershipStatus) }}</strong>
            <small>{{ $member->membershipType?->name ?? __('dashboard.membership_information') }}</small>
        </a>
    </section>

    @php($hasMemberAlerts = $overdueCount > 0 || $dueSoonBorrowings->isNotEmpty() || $membershipExpiringSoon || $outstandingFines->isNotEmpty() || $outstandingCompensation->isNotEmpty())
    <section class="member-dashboard-section" aria-labelledby="member-alerts-title">
        <div class="member-section-heading">
            <div><p class="member-eyebrow">{{ __('dashboard.account_updates') }}</p><h2 id="member-alerts-title">{{ __('dashboard.my_alerts') }}</h2></div>
            <a href="{{ route('member.alerts') }}">{{ __('dashboard.view_all') }}</a>
        </div>
        @if ($membershipExpiringSoon)<div class="alert alert-warning">{{ __('Your membership expires on :date.', ['date' => $member->expiry_date?->format('Y-m-d')]) }}</div>@endif
        @if ($overdueCount > 0)<div class="alert alert-danger">{{ __('You have :count overdue books.', ['count' => $overdueCount]) }} <a href="{{ route('member.borrowings') }}">{{ __('dashboard.view_my_borrowings') }}</a></div>@endif
        @if ($dueSoonBorrowings->isNotEmpty())<div class="alert alert-info">{{ __(':count books are due within seven days.', ['count' => $dueSoonBorrowings->count()]) }}</div>@endif
        @if ($outstandingFines->isNotEmpty())<div class="alert alert-warning">{{ __('You have :count pending fines.', ['count' => $outstandingFines->count()]) }} <a href="{{ route('member.fines') }}">{{ __('dashboard.view_my_fines') }}</a></div>@endif
        @if ($outstandingCompensation->isNotEmpty())<div class="alert alert-warning">{{ __('You have outstanding compensation for a lost or damaged book.') }} <a href="{{ route('member.payments') }}">{{ __('dashboard.view_my_payments') }}</a></div>@endif
        @unless ($hasMemberAlerts)<p class="member-empty-state">{{ __('dashboard.no_alerts') }}</p>@endunless
    </section>

    <section class="member-dashboard-section" id="catalog" aria-labelledby="catalog-title">
        <div class="member-section-heading">
            <div><p class="member-eyebrow">{{ $member->pradeshiyaSabha?->name ?? __('dashboard.member_portal') }}</p><h2 id="catalog-title">{{ __('dashboard.library_catalog') }}</h2></div>
            <a href="{{ route('member.books.search') }}">{{ __('dashboard.browse_all_books') }} <span aria-hidden="true">→</span></a>
        </div>
        <form class="member-catalog-filter" method="GET" action="{{ route('member.books.search') }}">
            <label class="visually-hidden" for="member-catalog-search">{{ __('Search books') }}</label>
            <input id="member-catalog-search" type="search" name="search" class="form-control" placeholder="{{ __('dashboard.search_title_author') }}">
            <label class="visually-hidden" for="member-catalog-category">{{ __('Category') }}</label>
            <select id="member-catalog-category" name="category_id" class="form-select">
                <option value="">{{ __('dashboard.all_categories') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
        </form>

        @if ($availableInventories->isNotEmpty())
            <div class="member-book-grid">
                @foreach ($availableInventories as $inventory)
                    @php($book = $inventory->book)
                    @if ($book)
                        <article class="member-book-card">
                            <a class="member-book-cover-link" href="{{ route('member.books.show', $book) }}" aria-label="{{ __('dashboard.view_book_details', ['title' => $book->title]) }}">
                                <img class="member-book-cover" src="{{ $book->cover_image_url }}" alt="{{ __('Cover of :title', ['title' => $book->title]) }}" loading="lazy">
                            </a>
                            <div class="member-book-copy">
                                <span class="member-availability">{{ __('Available') }}</span>
                                <h3><a href="{{ route('member.books.show', $book) }}">{{ $book->title }}</a></h3>
                                <p>{{ $book->author?->name ?? __('dashboard.author_not_listed') }}</p>
                                <small>{{ $book->category?->name ?? __('dashboard.category_not_listed') }}</small>
                            </div>
                        </article>
                    @endif
                @endforeach
            </div>
        @else
            <p class="member-empty-state">{{ __('dashboard.no_available_books_at_library') }}</p>
        @endif
    </section>

    <div class="member-dashboard-columns">
        <section class="member-dashboard-section" aria-labelledby="borrowed-title">
            <div class="member-section-heading">
                <div><p class="member-eyebrow">{{ __('dashboard.circulation') }}</p><h2 id="borrowed-title">{{ __('dashboard.current_borrowed_books') }}</h2></div>
                <a href="{{ route('member.borrowings') }}#renewals">{{ $availableRenewalCount }} {{ __('dashboard.available_renewals') }}</a>
            </div>
            @forelse ($activeBorrowings as $borrowing)
                @php($borrowingStatus = $borrowing->effectiveStatus())
                <article class="member-activity-row {{ $borrowingStatus === 'OVERDUE' ? 'member-activity-overdue' : ($dueSoonBorrowings->contains('id', $borrowing->id) ? 'member-activity-due-soon' : '') }}">
                    <div><strong>{{ $borrowing->book?->title ?? __('dashboard.book_unavailable') }}</strong><small>{{ __('dashboard.issued') }} {{ $borrowing->issue_date?->format('Y-m-d') ?? '—' }} · {{ __('dashboard.due') }} {{ $borrowing->due_date?->format('Y-m-d') ?? '—' }}</small></div>
                    <span class="member-activity-status">{{ __($borrowingStatus) }}</span>
                </article>
            @empty
                <p class="member-empty-state">{{ __('dashboard.no_checked_out_books') }}</p>
            @endforelse
        </section>

        <section class="member-dashboard-section" id="reservations" aria-labelledby="reservations-title">
            <div class="member-section-heading">
                <div><p class="member-eyebrow">{{ __('dashboard.library_services') }}</p><h2 id="reservations-title">{{ __('dashboard.my_reservations') }}</h2></div>
                <span class="member-count-chip">{{ $activeReservationCount }} {{ __('dashboard.active') }}</span>
            </div>
            @forelse ($reservations as $reservation)
                <article class="member-activity-row">
                    <div><strong>{{ $reservation->book?->title ?? __('dashboard.book_unavailable') }}</strong><small>{{ __('dashboard.reserved_on') }} {{ $reservation->reserved_at?->format('Y-m-d') ?? '—' }}</small></div>
                    <span class="member-activity-status">{{ __($reservation->status) }}</span>
                    @if ($reservation->status === 'PENDING')
                        <form method="POST" action="{{ route('member.reservations.cancel', $reservation) }}">
                            @csrf
                            <button class="btn btn-outline-secondary btn-sm" type="submit">{{ __('Cancel reservation') }}</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="member-empty-state">{{ __('dashboard.no_reservations') }}</p>
            @endforelse
            <p class="member-section-note">{{ __('dashboard.reservation_help_text') }}</p>
        </section>
    </div>

    <div class="member-dashboard-columns">
        <section class="member-dashboard-section" id="reading-history" aria-labelledby="history-title">
            <div class="member-section-heading">
                <div><p class="member-eyebrow">{{ __('dashboard.your_activity') }}</p><h2 id="history-title">{{ __('dashboard.reading_history') }}</h2></div>
                <a href="{{ route('member.borrowings') }}">{{ __('dashboard.view_all') }}</a>
            </div>
            @forelse ($borrowingHistory as $borrowing)
                <article class="member-activity-row">
                    <div><strong>{{ $borrowing->book?->title ?? __('dashboard.book_unavailable') }}</strong><small>{{ __('dashboard.issued') }} {{ $borrowing->issue_date?->format('Y-m-d') ?? '—' }}@if ($borrowing->return_date) · {{ __('dashboard.returned') }} {{ $borrowing->return_date->format('Y-m-d') }}@endif</small></div>
                    <span class="member-activity-status">{{ $borrowing->return_date ? __('dashboard.returned') : __($borrowing->effectiveStatus()) }}</span>
                </article>
            @empty
                <p class="member-empty-state">{{ __('Your borrowing history will appear here.') }}</p>
            @endforelse
        </section>

        <section class="member-dashboard-section" id="membership-details" aria-labelledby="membership-title">
            <div class="member-section-heading">
                <div><p class="member-eyebrow">{{ __('navigation.my_membership') }}</p><h2 id="membership-title">{{ __('dashboard.membership_information') }}</h2></div>
                <a href="{{ route('member.membership') }}">{{ __('dashboard.view_all') }}</a>
            </div>
            <dl class="member-detail-list">
                <div><dt>{{ __('dashboard.member_id') }}</dt><dd>{{ $member->member_id }}</dd></div>
                <div><dt>{{ __('dashboard.membership_type') }}</dt><dd>{{ $member->membershipType?->name ?? '—' }}</dd></div>
                <div><dt>{{ __('dashboard.registered_sabha') }}</dt><dd>{{ $member->pradeshiyaSabha?->name ?? __('dashboard.sabha_not_assigned') }}</dd></div>
                <div><dt>{{ __('dashboard.library') }}</dt><dd>{{ $member->pradeshiyaSabha?->settings?->firstWhere('key', 'library_name')?->value ?? $member->pradeshiyaSabha?->name ?? '—' }}</dd></div>
                <div><dt>{{ __('dashboard.membership_started') }}</dt><dd>{{ $member->membership_start_date?->format('Y-m-d') ?? '—' }}</dd></div>
                <div><dt>{{ __('dashboard.next_renewal') }}</dt><dd>{{ $member->next_renewal_date?->format('Y-m-d') ?? '—' }}</dd></div>
            </dl>
            @if ($member->pradeshiyaSabha?->phone || $member->pradeshiyaSabha?->address)
                <div class="member-library-contact">
                    @if ($member->pradeshiyaSabha?->phone)<span>{{ $member->pradeshiyaSabha->phone }}</span>@endif
                    @if ($member->pradeshiyaSabha?->address)<span>{{ $member->pradeshiyaSabha->address }}</span>@endif
                </div>
            @endif
        </section>
    </div>

    @if ($dueSoonBorrowings->isNotEmpty())
        <section class="member-dashboard-section member-due-soon" aria-labelledby="due-soon-title">
            <div class="member-section-heading"><div><p class="member-eyebrow">{{ __('dashboard.action_required') }}</p><h2 id="due-soon-title">{{ __('dashboard.due_soon') }}</h2></div><a href="{{ route('member.borrowings') }}">{{ __('dashboard.view_all') }}</a></div>
            @foreach ($dueSoonBorrowings as $borrowing)
                <article class="member-activity-row"><div><strong>{{ $borrowing->book?->title ?? __('dashboard.book_unavailable') }}</strong><small>{{ __('dashboard.due') }} {{ $borrowing->due_date?->format('Y-m-d') ?? '—' }}</small></div><span class="member-activity-status">{{ __('dashboard.upcoming') }}</span></article>
            @endforeach
        </section>
    @endif
</div>
@endsection
