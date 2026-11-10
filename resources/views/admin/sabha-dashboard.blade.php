@extends('layouts.admin')

@section('title', __('dashboard.title').' · '.$libraryName)

@section('content')
<div class="dashboard-page dashboard-sabha-admin">
    <div class="ps-dashboard-welcome">
        <div>
            <p class="ps-dashboard-eyebrow">{{ $pradeshiyaSabha->name }}</p>
            <h1>{{ __('dashboard.welcome_back') }}, {{ __('dashboard.library_admin') }} · {{ auth()->user()->name }}</h1>
            <p>{{ __('dashboard.library_overview') }}</p>
            <time class="dashboard-local-clock" data-sri-lanka-clock datetime="{{ now()->toIso8601String() }}" aria-label="{{ __('Current Sri Lankan date and time') }}">{{ now()->format('Y-m-d H:i:s') }}</time>
        </div>
        <a class="ps-settings-shortcut" href="{{ route('admin.settings.index') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="m19.4 15 .1.1 1.4 1.1-1.5 2.6-1.7-.6a8 8 0 0 1-1.5.9l-.3 1.8h-3l-.3-1.8a8 8 0 0 1-1.5-.9l-1.7.6-1.5-2.6L7.3 15a8 8 0 0 1 0-1.8l-1.4-1.1 1.5-2.6 1.7.6a8 8 0 0 1 1.5-.9l.3-1.8h3l.3 1.8a8 8 0 0 1 1.5.9l1.7-.6 1.5 2.6-1.4 1.1a8 8 0 0 1-.1 1.8Z"></path></svg>
            {{ __('dashboard.library_settings') }}
        </a>
    </div>

    <section class="ps-stat-grid sabha-dashboard-stat-grid" aria-label="{{ __('dashboard.summary') }}">
        @foreach ([
            ['total_members', $memberStats['total'], route('admin.members.index')],
            ['total_books', $bookStats['copies'], route('admin.books.index')],
            ['available_books', $bookStats['available'], route('admin.books.search', ['availability' => 'AVAILABLE'])],
            ['active_librarians', $activeLibrarianCount, null],
            ['currently_borrowed', $borrowingStats['issued'], route('admin.borrowings.index')],
            ['overdue_books', $borrowingStats['overdue'], route('admin.borrowings.index', ['status' => 'OVERDUE'])],
            ['today_issues', $borrowingStats['todayIssues'], route('admin.borrowings.index')],
            ['today_returns', $borrowingStats['todayReturns'], route('admin.borrowings.index')],
            ['pending_applications', $pendingApplicationCount, route('admin.member-applications.index')],
            ['unpaid_fines', $alerts['unpaidFineRecords']->count(), route('admin.fines.index')],
            ['lost_books', $operationalStats['lostBooks'], route('admin.lost-damaged.index')],
            ['damaged_books', $operationalStats['damagedBooks'], route('admin.lost-damaged.index')],
        ] as [$label, $value, $href])
            @if ($href)
                <a class="sabha-summary-item" href="{{ $href }}"><span>{{ __('dashboard.'.$label) }}</span><strong>{{ number_format($value) }}</strong></a>
            @else
                <div class="sabha-summary-item"><span>{{ __('dashboard.'.$label) }}</span><strong>{{ number_format($value) }}</strong></div>
            @endif
        @endforeach
    </section>

    <section class="ps-dashboard-panel ps-recent-panel member-applications-dashboard" aria-labelledby="member-applications-title">
        <div class="ps-panel-heading">
            <div><p>{{ __('member_applications.review_applications') }}</p><h2 id="member-applications-title">{{ __('member_applications.pending_applications') }} <span class="badge text-bg-warning">{{ $pendingApplicationCount }}</span></h2></div>
            <a class="ps-panel-view-all" href="{{ route('admin.member-applications.index') }}">{{ __('member_applications.view_all') }} <span aria-hidden="true">→</span></a>
        </div>
        @if ($pendingApplications->isNotEmpty())
            <div class="table-responsive">
                <table class="table ps-activity-table align-middle mb-0">
                    <thead><tr><th>{{ __('member_applications.applicant') }}</th><th>{{ __('Email') }}</th><th>{{ __('member_applications.submitted') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                    <tbody>@foreach ($pendingApplications as $application)
                        <tr><td>{{ $application->full_name }}</td><td>{{ $application->email }}</td><td>{{ $application->registration_date?->format('Y-m-d') ?? '—' }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.member-applications.show', $application) }}">{{ __('member_applications.review') }}</a></td></tr>
                    @endforeach</tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">{{ __('member_applications.no_pending_applications') }}</p>
        @endif
    </section>

    <section class="ps-dashboard-charts" aria-label="{{ __('Library charts') }}">
        <article class="ps-dashboard-panel ps-issues-panel">
            <div class="ps-panel-heading">
                <div><p>{{ __('dashboard.borrowing_activity') }}</p><h2>{{ __('dashboard.monthly_book_issues') }}</h2></div>
                <span class="ps-panel-meta">{{ $year }}</span>
            </div>
            <div class="ps-chart-wrap ps-bar-chart-wrap">
                <canvas id="monthlyBookIssues" role="img" aria-label="{{ __('Monthly book issues for :year', ['year' => $year]) }}"></canvas>
            </div>
        </article>
        <article class="ps-dashboard-panel ps-category-panel">
            <div class="ps-panel-heading">
                <div><p>{{ __('dashboard.collection_overview') }}</p><h2>{{ __('dashboard.books_by_category') }}</h2></div>
                <a href="{{ route('admin.categories.index') }}">{{ __('Manage') }} {{ __('Categories') }}</a>
            </div>
            @if ($categoryStats['total'] > 0)
                <div class="ps-chart-wrap ps-donut-chart-wrap">
                    <canvas id="booksByCategory" role="img" aria-label="{{ __('Books by library category') }}"></canvas>
                <div class="ps-donut-total"><strong>{{ number_format($categoryStats['total']) }}</strong><span>{{ __('Book titles') }}</span></div>
                </div>
                <div class="ps-chart-legend" id="categoryLegend" aria-label="{{ __('Book category legend') }}"></div>
            @else
                <div class="ps-chart-empty">
                    <span aria-hidden="true">◎</span>
                    <strong>{{ __('dashboard.no_category_data') }}</strong>
                    <p>{{ __('dashboard.category_data_hint') }}</p>
                </div>
            @endif
        </article>
    </section>

    <section class="ps-dashboard-panel ps-recent-panel" aria-labelledby="recent-activity-title">
        <div class="ps-panel-heading">
            <div><p>{{ __('dashboard.latest_transactions') }}</p><h2 id="recent-activity-title">{{ __('dashboard.recent_activity') }}</h2></div>
            <a class="ps-panel-view-all" href="{{ route('admin.borrowings.index') }}">{{ __('View') }} {{ __('All') }} <span aria-hidden="true">→</span></a>
        </div>
        <x-admin.recent-activity-table :borrowings="$recent['borrowings']" />
    </section>

    <section class="ps-dashboard-panel ps-recent-panel" aria-labelledby="recent-members-title">
        <div class="ps-panel-heading">
            <div><p>{{ __('New to the library') }}</p><h2 id="recent-members-title">{{ __('dashboard.recent_registrations') }}</h2></div>
            <a class="ps-panel-view-all" href="{{ route('admin.members.index') }}">{{ __('View') }} {{ __('Members') }} <span aria-hidden="true">→</span></a>
        </div>
        @if ($recentMembers->isNotEmpty())
            <div class="table-responsive">
                <table class="table ps-activity-table align-middle mb-0">
                    <thead><tr><th>{{ __('Member') }}</th><th>{{ __('Member ID') }}</th><th>{{ __('Registered') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @foreach ($recentMembers as $member)
                            <tr><td>{{ $member->full_name }}</td><td>{{ $member->member_id }}</td><td>{{ $member->registration_date?->format('Y-m-d') ?? '—' }}</td><td>{{ __($member->effectiveStatus()) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">{{ __('dashboard.new_member_registrations') }}</p>
        @endif
    </section>

    <section class="ps-quick-actions" aria-labelledby="quick-actions-title">
        <div class="ps-quick-actions-heading">
            <div><p>{{ __('dashboard.common_tasks') }}</p><h2 id="quick-actions-title">{{ __('dashboard.quick_actions') }}</h2></div>
        </div>
        <div class="ps-quick-actions-grid dashboard-quick-action-grid">
            <x-admin.action-link :href="route('admin.books.index')" title="{{ __('Book Management') }}" :description="__('Browse and manage catalogue records')">
                <x-slot:icon><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21V5.5Z"></path><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20M8 7h8"></path></svg></x-slot:icon>
            </x-admin.action-link>
            <x-admin.action-link :href="route('admin.books.create')" title="{{ __('Add New Book') }}" :description="__('Add a title to the library catalogue')">
                <x-slot:icon><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21V5.5Z"></path><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20M12 7v5m-2.5-2.5h5"></path></svg></x-slot:icon>
            </x-admin.action-link>
            <x-admin.action-link :href="route('admin.members.index')" title="{{ __('Member Management') }}" :description="__('Review registered library members')">
                <x-slot:icon><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20a6.5 6.5 0 0 1 13 0M18 8h5m-2.5-2.5v5"></path></svg></x-slot:icon>
            </x-admin.action-link>
            <x-admin.action-link :href="route('admin.members.create')" title="{{ __('Register Member') }}"  :description="__('Add a new library member')">
                <x-slot:icon><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20a6.5 6.5 0 0 1 13 0M18 8h5m-2.5-2.5v5"></path></svg></x-slot:icon>
            </x-admin.action-link>
            <x-admin.action-link :href="route('admin.issue-books')" title="{{ __('Issue Book') }}"  :description="__('Record a new borrowing')">
                <x-slot:icon><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5"></path><path d="M4 5h6M4 19h6"></path></svg></x-slot:icon>
            </x-admin.action-link>
            <x-admin.action-link :href="route('admin.returns.index')" title="{{ __('Return Book') }}"  :description="__('Open the returns desk')">
                <x-slot:icon><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m5 5-5-5 5-5"></path><path d="M20 5h-6M20 19h-6"></path></svg></x-slot:icon>
            </x-admin.action-link>
            <x-admin.action-link :href="route('admin.borrowings.index', ['status' => 'ISSUED'])" title="{{ __('Issued Books') }}" :description="__('View current active loans')">
                <x-slot:icon><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"></path></svg></x-slot:icon>
            </x-admin.action-link>
            <x-admin.action-link :href="route('admin.borrowings.index', ['status' => 'OVERDUE'])" title="{{ __('Overdue Books') }}" :description="__('Review loans past their due date')">
                <x-slot:icon><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg></x-slot:icon>
            </x-admin.action-link>
            <x-admin.action-link :href="route('admin.reports.index')" title="{{ __('Reports and Analytics') }}" :description="__('Open library reports and analytics')">
                <x-slot:icon><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h17M8 15l4-4 3 2 5-6"></path></svg></x-slot:icon>
            </x-admin.action-link>
        </div>
    </section>

    @php($hasAlerts = $alerts['overdueRecords']->isNotEmpty() || $alerts['membershipRenewals']->isNotEmpty() || $alerts['unpaidFineRecords']->isNotEmpty() || $alerts['lostBookRecords']->isNotEmpty() || $alerts['damageRecords']->isNotEmpty())
    <section id="dashboard-alerts" class="sabha-dashboard-alerts ps-alerts-panel" aria-labelledby="dashboard-alerts-title">
        <div class="ps-panel-heading">
            <div><p>{{ __('Needs attention') }}</p><h2 id="dashboard-alerts-title">{{ __('Library alerts') }}</h2></div>
            @unless ($hasAlerts)<span class="text-muted">{{ __('No alerts at this time.') }}</span>@endunless
        </div>

        <div class="row g-3">
            <div class="col-12">
                <section class="sabha-alert-section" aria-labelledby="overdue-alert-title">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h3 id="overdue-alert-title" class="h6 mb-0">{{ __('Overdue Books') }} <span class="badge text-bg-danger ms-1">{{ $alerts['overdue'] }}</span></h3>
                        <a href="{{ route('admin.borrowings.index', ['status' => 'OVERDUE']) }}">{{ __('View') }} {{ __('Borrowings') }}</a>
                    </div>
                    @if ($alerts['overdueRecords']->isEmpty())
                        <p class="text-muted mb-0">{{ __('No overdue books.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>{{ __('Member') }}</th><th>{{ __('Member ID') }}</th><th>{{ __('Book') }}</th><th>{{ __('Due date') }}</th><th>{{ __('Overdue') }}</th><th>{{ __('Status') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($alerts['overdueRecords'] as $borrowing)
                                        @php($overdueDays = $borrowing->due_date->diffInDays(today()))
                                        <tr>
                                            <td>{{ $borrowing->member?->full_name ?? __('Member unavailable') }}</td>
                                            <td>{{ $borrowing->member?->member_id ?? '—' }}</td>
                                            <td>{{ $borrowing->book?->title ?? __('Book unavailable') }}</td>
                                            <td>{{ $borrowing->due_date->format('Y-m-d') }}</td>
                                            <td>{{ __(':days days overdue', ['days' => $overdueDays]) }}</td>
                                            <td>{{ __($borrowing->effectiveStatus()) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="sabha-alert-section h-100" aria-labelledby="membership-alert-title">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h3 id="membership-alert-title" class="h6 mb-0">{{ __('Membership expiry and renewal') }}</h3>
                        <a href="{{ route('admin.members.index') }}">{{ __('View members') }}</a>
                    </div>
                    @forelse ($alerts['membershipRenewals'] as $member)
                        <div class="sabha-alert-row"><span>{{ $member->full_name }} <small class="text-muted">{{ $member->member_id }}</small></span><span>{{ $member->effectiveExpiryDate()?->format('Y-m-d') ?? '—' }} · {{ __($member->effectiveStatus()) }}</span></div>
                    @empty
                        <p class="text-muted mb-0">{{ __('No membership expiry alerts.') }}</p>
                    @endforelse
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="sabha-alert-section h-100" aria-labelledby="fine-alert-title">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h3 id="fine-alert-title" class="h6 mb-0">{{ __('Pending fines') }} <span class="badge text-bg-warning ms-1">{{ $alerts['unpaidFines'] }}</span></h3>
                        <a href="{{ route('admin.fines.index', ['status' => 'UNPAID']) }}">{{ __('View') }} {{ __('Fines') }} / {{ __('Payments') }}</a>
                    </div>
                    @forelse ($alerts['unpaidFineRecords'] as $fine)
                        <div class="sabha-alert-row"><span>{{ $fine->member?->full_name ?? 'Member unavailable' }}</span><span>{{ number_format((float) $fine->amount, 2) }} · {{ __($fine->status) }}</span></div>
                    @empty
                        <p class="text-muted mb-0">{{ __('No pending fines.') }}</p>
                    @endforelse
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="sabha-alert-section h-100" aria-labelledby="lost-alert-title">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h3 id="lost-alert-title" class="h6 mb-0">{{ __('Lost Books') }}</h3>
                        <a href="{{ route('admin.lost-damaged.index') }}#lost-books">{{ __('View lost books') }}</a>
                    </div>
                    @forelse ($alerts['lostBookRecords'] as $lostBook)
                        <div class="sabha-alert-row"><span>{{ $lostBook->book?->title ?? 'Book unavailable' }}</span><span>{{ $lostBook->member?->full_name ?? 'Member unavailable' }} · {{ __($lostBook->status) }}</span></div>
                    @empty
                        <p class="text-muted mb-0">{{ __('No lost books.') }}</p>
                    @endforelse
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="sabha-alert-section h-100" aria-labelledby="damage-alert-title">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h3 id="damage-alert-title" class="h6 mb-0">{{ __('Damaged Books') }}</h3>
                        <a href="{{ route('admin.lost-damaged.index') }}#damage-records">{{ __('View damaged books') }}</a>
                    </div>
                    @forelse ($alerts['damageRecords'] as $damage)
                        <div class="sabha-alert-row"><span>{{ $damage->book?->title ?? 'Book unavailable' }}</span><span>{{ $damage->member?->full_name ?? 'Member unavailable' }} · {{ __($damage->status) }}</span></div>
                    @empty
                        <p class="text-muted mb-0">{{ __('No damaged books.') }}</p>
                    @endforelse
                </section>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const issueCanvas = document.getElementById('monthlyBookIssues');
        if (issueCanvas && window.Chart) {
            new Chart(issueCanvas, {
                type: 'bar',
                data: {
                    labels: @json($monthlyStats['labels']),
                    datasets: [{
                        label: @js(__('Books issued')),
                        data: @json($monthlyStats['issues']),
                        backgroundColor: 'rgba(98, 217, 155, 0.78)',
                        hoverBackgroundColor: '#087a4d',
                        borderRadius: 5,
                        maxBarThickness: 30,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#b8d5c7' } },
                        y: { beginAtZero: true, grid: { color: 'rgba(184, 213, 199, 0.12)' }, ticks: { precision: 0, color: '#b8d5c7' } },
                    },
                },
            });
        }

        const categoryCanvas = document.getElementById('booksByCategory');
        if (categoryCanvas && window.Chart) {
            const labels = @json($categoryStats['labels']);
            const values = @json($categoryStats['data']);
            const colors = ['#62d99b', '#ffd54a', '#087b50', '#a4e7c3', '#61b5a0', '#b8d5c7'];
            const chart = new Chart(categoryCanvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors.slice(0, values.length),
                        borderColor: '#075b40',
                        borderWidth: 4,
                        hoverOffset: 5,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: { legend: { display: false } },
                },
            });

            const legend = document.getElementById('categoryLegend');
            labels.forEach(function (label, index) {
                const item = document.createElement('span');
                item.className = 'ps-chart-legend-item';
                item.innerHTML = '<i style="--legend-color:' + colors[index % colors.length] + '"></i><span></span><strong></strong>';
                item.querySelector('span').textContent = label;
                item.querySelector('strong').textContent = Number(values[index]).toLocaleString();
                legend.appendChild(item);
            });
        }
    });
</script>
@endsection