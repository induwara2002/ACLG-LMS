@extends('layouts.librarian')

@section('title', __('dashboard.librarian'))

@section('content')
<div class="dashboard-page dashboard-librarian librarian-dashboard">
    <header class="role-dashboard-heading"><div><p class="section-kicker">{{ __('dashboard.daily_operations') }}</p><h1>{{ __('dashboard.good_day') }} {{ auth()->user()->name }}</h1><p>{{ __('dashboard.overview') }}</p><time class="dashboard-local-clock" data-sri-lanka-clock datetime="{{ now()->toIso8601String() }}" aria-label="{{ __('Current Sri Lankan date and time') }}">{{ now()->format('Y-m-d H:i:s') }}</time></div><a class="btn btn-primary" href="{{ route('librarian.issue-books') }}">{{ __('dashboard.issue_book') }}</a></header>
    <section class="role-stat-grid" aria-label="{{ __('dashboard.library_operations') }}">
        @foreach ([['book_titles', $bookStats['titles'], '▤'], ['total_copies', $bookStats['copies'], '▥'], ['available_copies', $bookStats['available'], '✓'], ['currently_issued', $bookStats['issued'], '↗'], ['today_issues', $operationalStats['todayIssues'], '＋'], ['today_returns', $operationalStats['todayReturns'], '↙'], ['books_due_today', $operationalStats['booksDueToday'], '◷'], ['overdue', $operationalStats['overdue'], '!'], ['active_reservations', $operationalStats['activeReservations'], '◉'], ['active_members', $memberStats['active'], '♙'], ['lost_books', $operationalStats['lost'], '×'], ['damaged_books', $operationalStats['damaged'], '◇']] as [$label, $value, $icon])
            <article class="role-stat-card"><span class="role-stat-icon" aria-hidden="true">{{ $icon }}</span><span>{{ __('dashboard.'.$label) }}</span><strong>{{ number_format($value) }}</strong></article>
        @endforeach
    </section>
    <section class="role-quick-actions">
        <div class="role-section-heading"><div><p class="section-kicker">{{ __('dashboard.frequently_used') }}</p><h2>{{ __('dashboard.quick_actions') }}</h2></div></div>
        <div class="librarian-action-grid">
            <a href="{{ route('librarian.books.search') }}">{{ __('Book Management') }} <span>{{ __('Search and review the catalogue') }} →</span></a>
            <a href="{{ route('librarian.members.search') }}">{{ __('dashboard.search_member') }} <span>{{ __('dashboard.find_registered_member') }} →</span></a>
            <a href="{{ route('librarian.issue-books') }}">{{ __('Issue a Book') }} <span>{{ __('dashboard.record_borrowing') }} →</span></a>
            <a href="{{ route('librarian.returns.index') }}">{{ __('Return a Book') }} <span>{{ __('dashboard.open_return_desk') }} →</span></a>
            <a href="{{ route('librarian.borrowings.index', ['status' => 'ISSUED']) }}">{{ __('Issued Books') }} <span>{{ __('View active loans') }} →</span></a>
            <a href="{{ route('librarian.borrowings.index', ['status' => 'OVERDUE']) }}">{{ __('Overdue Books') }} <span>{{ __('Review loans past their due date') }} →</span></a>
            <a href="{{ route('librarian.reports.index') }}">{{ __('Reports and Analytics') }} <span>{{ __('Open library reports') }} →</span></a>
        </div>
    </section>
    <section class="role-dashboard-panel">
        <div class="role-section-heading"><div><p class="section-kicker">{{ __('circulation.borrowing_books') }}</p><h2>{{ __('dashboard.recent_borrowing_activity') }}</h2></div><a href="{{ route('librarian.borrowings.index') }}">{{ __('dashboard.view_all') }}</a></div>
        @if ($recent['borrowings']->isNotEmpty())
            <div class="table-responsive"><table class="table align-middle role-data-table mb-0"><thead><tr><th>{{ __('Member') }}</th><th>{{ __('Book') }}</th><th>{{ __('Issue date') }}</th><th>{{ __('Due date') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
                @foreach ($recent['borrowings'] as $borrowing)
                    <tr><td>{{ $borrowing->member?->full_name ?? __('dashboard.member_unavailable') }}</td><td>{{ $borrowing->book?->title ?? __('dashboard.book_unavailable') }}</td><td>{{ $borrowing->issue_date?->format('Y-m-d') ?? '—' }}</td><td>{{ $borrowing->due_date?->format('Y-m-d') ?? '—' }}</td><td><x-admin.status-badge :status="$borrowing->effectiveStatus()" /></td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <p class="empty-state">{{ __('dashboard.no_borrowing_activity') }}</p>
        @endif
    </section>
    <section class="role-dashboard-panel">
        <div class="role-section-heading"><div><p class="section-kicker">{{ __('dashboard.needs_attention') }}</p><h2>{{ __('dashboard.overdue_alerts') }}</h2></div><a href="{{ route('librarian.borrowings.index', ['status' => 'OVERDUE']) }}">{{ __('dashboard.overdue_books') }}</a></div>
        @forelse ($alerts['overdueRecords'] as $borrowing)
            <div class="member-list-row"><span><strong>{{ $borrowing->book?->title ?? __('dashboard.book_unavailable') }}</strong><small>{{ $borrowing->member?->full_name ?? __('dashboard.member_unavailable') }}</small></span><span class="badge text-bg-danger">{{ __('dashboard.due') }} {{ $borrowing->due_date?->format('Y-m-d') ?? '—' }}</span></div>
        @empty
            <p class="empty-state">{{ __('dashboard.no_overdue_books') }}</p>
        @endforelse
    </section>
</div>
@endsection
