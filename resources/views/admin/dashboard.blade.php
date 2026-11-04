@extends('layouts.admin')

@section('title', $pradeshiyaSabha?->name ?? __('Admin Dashboard'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        @if ($pradeshiyaSabha)
            <div class="d-flex align-items-center gap-3 mb-2">
                <img src="{{ $pradeshiyaSabha->logo_path ? route('sabhas.logo', $pradeshiyaSabha) : asset('images/government-logo.png') }}" alt="{{ __('Logo for :name', ['name' => $pradeshiyaSabha->name]) }}" width="64" height="64" class="object-fit-contain">
                <div>
                    <h1 class="page-title">{{ $pradeshiyaSabha->name }}</h1>
                    <p class="page-subtitle mb-0">{{ __('Pradeshiya Sabha Admin') }}</p>
                </div>
            </div>
            @if (auth()->user()?->isMainAdmin() && isset($managementUrl))
                <a class="btn btn-sm btn-outline-secondary" href="{{ $managementUrl }}">{{ __('Manage Sabha') }}</a>
            @endif
        @else
            <h1 class="page-title">{{ __('dashboard.super_admin') }}</h1>
            <p class="page-subtitle">{{ __('Pradeshiya Sabha administration and system-wide library overview.') }}</p>
            <time class="dashboard-local-clock" data-sri-lanka-clock datetime="{{ now()->toIso8601String() }}" aria-label="{{ __('Current Sri Lankan date and time') }}">{{ now()->format('Y-m-d H:i:s') }}</time>
        @endif
    </div>
</div>

@if (auth()->user()?->isPradeshiyaSabhaAdmin())
    <div class="d-flex flex-wrap gap-2 mb-4" aria-label="{{ __('Library actions') }}">
        <a class="btn btn-primary" href="{{ route('admin.members.create') }}">{{ __('Register Member') }}</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.members.index') }}">{{ __('Members') }}</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.books.index') }}">{{ __('Books') }}</a>
        <a class="btn btn-primary" href="{{ route('admin.issue-books') }}">{{ __('Issue Book') }}</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.borrowings.index') }}">{{ __('Borrowings') }}</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.returns.index') }}">{{ __('Returns') }}</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.renewals.index') }}">{{ __('Renewals') }}</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.payments.index') }}">{{ __('Payments') }}</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.fines.index') }}">{{ __('Fines') }}</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.lost-damaged.index') }}">{{ __('Lost Books') }}</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.lost-damaged.index') }}">{{ __('Damaged Books') }}</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.reports.index') }}">{{ __('Reports') }}</a>
    </div>
@endif

@if ($sabhaTotals)
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card h-100 p-3">
                <div class="text-muted small">{{ __('Total Pradeshiya Sabhas') }}</div>
                <div class="h2 fw-bold mt-2 mb-0">{{ $sabhaTotals['total'] }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card h-100 p-3">
                <div class="text-muted small">{{ __('Active Pradeshiya Sabhas') }}</div>
                <div class="h2 fw-bold mt-2 mb-0">{{ $sabhaTotals['active'] }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card h-100 p-3">
                <div class="text-muted small">{{ __('Active Sabha Admins') }}</div>
                <div class="h2 fw-bold mt-2 mb-0">{{ $sabhaTotals['activeAdmins'] }}</div>
            </div>
        </div>
    </div>
@endif

@if ($sabhas->isNotEmpty())
    <section class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">{{ __('Pradeshiya Sabha Management') }}</h2>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.sabhas.index') }}">{{ __('Manage all') }}</a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead><tr><th>{{ __('Sabha') }}</th><th>{{ __('Code') }}</th><th>{{ __('Admin') }}</th><th>{{ __('Members') }}</th><th>{{ __('Books') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Overdue') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($sabhas as $sabha)
                        <tr>
                            <td>{{ $sabha->name }}</td>
                            <td>{{ $sabha->code }}</td>
                            <td>{{ $sabha->adminAssignment?->is_active && $sabha->adminAssignment?->user?->is_active ? $sabha->adminAssignment->user->name : __(__('Not configured')) }}</td>
                            <td>{{ $sabha->members_count }}</td>
                            <td>{{ $sabha->books_count }}</td>
                            <td>{{ $sabha->active_borrowings_count }}</td>
                            <td>{{ $sabha->overdue_count }}</td>
                            <td>{{ __($sabha->is_active ? __('Active') : __('Inactive')) }}</td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.sabhas.dashboard', $sabha) }}">{{ __('View Sabha Dashboard') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

@if (auth()->user()?->isMainAdmin())
    <section class="role-quick-actions mb-4">
        <div class="role-section-heading"><div><p class="section-kicker">{{ __('System administration') }}</p><h2>{{ __('Quick Actions') }}</h2></div></div>
        <div class="librarian-action-grid">
            <a href="{{ route('admin.books.index') }}">{{ __('Book Management') }} <span>{{ __('Browse library books') }} →</span></a>
            <a href="{{ route('admin.members.index') }}">{{ __('Member Management') }} <span>{{ __('Browse registered members') }} →</span></a>
            <a href="{{ route('admin.borrowings.index', ['status' => 'ISSUED']) }}">{{ __('Issued Books') }} <span>{{ __('View current loans') }} →</span></a>
            <a href="{{ route('admin.borrowings.index', ['status' => 'OVERDUE']) }}">{{ __('Overdue Books') }} <span>{{ __('Review overdue loans') }} →</span></a>
            <a href="{{ route('admin.sabhas.create') }}">{{ __('Register a Pradeshiya Sabha') }} <span>{{ __('Set up a new Sabha') }} →</span></a>
            <a href="{{ route('admin.sabhas.index') }}">{{ __('Manage Sabha admins') }} <span>{{ __('Review Sabha assignments') }} →</span></a>
            <a href="{{ route('admin.audit-logs.index') }}">{{ __('Review system activity') }} <span>{{ __('Open the audit log') }} →</span></a>
            <a href="{{ route('admin.reports.index') }}">{{ __('View reports') }} <span>{{ __('System reporting') }} →</span></a>
        </div>
    </section>
    <section class="role-dashboard-panel mb-4">
        <div class="role-section-heading"><div><p class="section-kicker">{{ __('Latest transactions') }}</p><h2>{{ __('Recent Activity') }}</h2></div><a href="{{ route('admin.borrowings.index') }}">{{ __('View all') }}</a></div>
        @if ($recent['borrowings']->isNotEmpty())
            <div class="table-responsive"><table class="table align-middle role-data-table mb-0"><thead><tr><th>{{ __('Member') }}</th><th>{{ __('Book') }}</th><th>{{ __('Issue date') }}</th><th>{{ __('Due date') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
                @foreach ($recent['borrowings'] as $borrowing)
                    <tr><td>{{ $borrowing->member?->full_name ?? __('Member unavailable') }}</td><td>{{ $borrowing->book?->title ?? __('Book unavailable') }}</td><td>{{ $borrowing->issue_date?->format('Y-m-d') ?? '—' }}</td><td>{{ $borrowing->due_date?->format('Y-m-d') ?? '—' }}</td><td><x-admin.status-badge :status="$borrowing->effectiveStatus()" /></td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <p class="empty-state">{{ __('No borrowing activity is recorded yet.') }}</p>
        @endif
    </section>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Book titles') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $bookStats['titles'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Total physical copies') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $bookStats['copies'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Available copies') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $bookStats['available'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Issued Books') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $borrowingStats['issued'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Overdue Books') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $borrowingStats['overdue'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Lost Books') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $operationalStats['lostBooks'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Damaged Books') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $operationalStats['damagedBooks'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Lost copies') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $bookStats['lost'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Damaged copies') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $bookStats['damaged'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Repair copies') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $bookStats['repair'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Total members') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $memberStats['total'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Active members') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $memberStats['active'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Expired members') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $memberStats['expired'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Suspended members') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $memberStats['suspended'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Children memberships') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $memberStats['children'] }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card h-100 p-3">
            <div class="text-muted small">{{ __('Elders memberships') }}</div>
            <div class="display-6 fw-bold mt-2">{{ $memberStats['elders'] }}</div>
        </div>
    </div>
</div>
<div class="row g-4 mb-4"><div class="col-lg-6"><div class="card"><div class="card-body"><h2 class="h5">{{ __('Borrowing') }}</h2><div class="row g-3"><div class="col-6">{{ __('Issued') }}<strong class="d-block">{{ $borrowingStats['issued'] }}</strong></div><div class="col-6">{{ __('Overdue') }}<strong class="d-block">{{ $borrowingStats['overdue'] }}</strong></div><div class="col-6">{{ __('Returned') }}<strong class="d-block">{{ $borrowingStats['returned'] }}</strong></div><div class="col-6">{{ __('Renewals') }}<strong class="d-block">{{ $borrowingStats['renewals'] }}</strong></div><div class="col-6">{{ __('Today\'s Issues') }}<strong class="d-block">{{ $borrowingStats['todayIssues'] }}</strong></div><div class="col-6">{{ __('Today\'s Returns') }}<strong class="d-block">{{ $borrowingStats['todayReturns'] }}</strong></div></div></div></div></div><div class="col-lg-6"><div class="card"><div class="card-body"><h2 class="h5">{{ __('Financial totals') }}</h2><div class="row g-2"><div class="col-6">{{ __('Total Payments') }}<strong class="d-block">{{ $financialStats['paymentCount'] }}</strong></div><div class="col-6">{{ __('Registration') }}<br><strong>{{ number_format((float) $financialStats['registration'], 2) }}</strong></div><div class="col-6">{{ __('Membership') }}<br><strong>{{ number_format((float) $financialStats['monthlyMembership'], 2) }}</strong></div><div class="col-6">{{ __('Fines paid') }}<br><strong>{{ number_format((float) $financialStats['overdueFines'], 2) }}</strong></div><div class="col-6">{{ __('Compensation') }}<br><strong>{{ number_format((float) $financialStats['damageCompensation'] + (float) $financialStats['lostCompensation'], 2) }}</strong></div><div class="col-6">{{ __('Outstanding Fines') }}<br><strong>{{ number_format((float) $financialStats['unpaidFines'], 2) }}</strong></div><div class="col-6">{{ __('Outstanding compensation') }}<br><strong>{{ number_format((float) $financialStats['outstandingCompensation'], 2) }}</strong></div></div></div></div></div></div>
<div class="row g-4"><div class="col-lg-8"><div class="card"><div class="card-body"><h2 class="h5">{{ __('Monthly activity for') }} {{ $year }}</h2><div class="monthly-activity-scroll"><div class="monthly-activity-chart"><canvas id="monthlyActivityChart" role="img" aria-label="{{ __('Monthly activity chart') }}" height="110"></canvas></div></div></div></div></div><div class="col-lg-4"><div class="card"><div class="card-body"><h2 class="h5">{{ __('Alerts') }}</h2>@foreach ([['Overdue', $alerts['overdue']], ['Expired members', $alerts['expiredMembers']], ['Unpaid fines', $alerts['unpaidFines']], ['Unpaid compensation', $alerts['unpaidCompensation']], ['Repair', $alerts['repair']]] as [$label, $value])<div class="d-flex justify-content-between border-bottom py-2"><span>{{ __($label) }}</span><strong>{{ $value }}</strong></div>@endforeach</div></div></div></div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('monthlyActivityChart'), {
        type: 'line',
        data: {
            labels: @json($monthlyStats['labels']),
            datasets: [
                {label: @js(__('Issues')), data: @json($monthlyStats['issues']), borderColor: '#0d6efd'},
                {label: @js(__('Returns')), data: @json($monthlyStats['returns']), borderColor: '#198754'},
                {label: @js(__('Members')), data: @json($monthlyStats['members']), borderColor: '#fd7e14'}
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    ticks: {
                        autoSkip: false,
                        minRotation: 0,
                        maxRotation: 0
                    }
                }
            }
        }
    });
</script>
@endsection
