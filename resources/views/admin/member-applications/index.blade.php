@extends('layouts.admin')

@section('title', __('member_applications.title'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><p class="section-kicker">{{ auth()->user()->pradeshiyaSabha?->name }}</p><h1 class="page-title">{{ __('member_applications.title') }}</h1><p class="page-subtitle">{{ __('member_applications.intro') }}</p></div>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">{{ __('navigation.dashboard') }}</a>
</div>
<section class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.member-applications.index') }}" class="row g-3 align-items-end">
            <div class="col-md-6"><label class="form-label" for="application-search">{{ __('member_applications.search_label') }}</label><input id="application-search" type="search" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('member_applications.search_placeholder') }}"></div>
            <div class="col-md-4"><label class="form-label" for="application-status">{{ __('Status') }}</label><select id="application-status" name="status" class="form-select">@foreach (['PENDING', 'ACTIVE', 'REJECTED', 'SUSPENDED', 'EXPIRED', 'ALL'] as $option)<option value="{{ $option }}" @selected($status === $option)>{{ $option === 'ALL' ? __('member_applications.all_statuses') : __($option) }}</option>@endforeach</select></div>
            <div class="col-md-2 d-grid"><button class="btn btn-primary" type="submit">{{ __('Search') }}</button></div>
        </form>
    </div>
</section>
<section class="card">
    <div class="card-body p-0">
        @if ($applications->isNotEmpty())
            <div class="table-responsive"><table class="table table-striped align-middle mb-0">
                <thead><tr><th>{{ __('member_applications.submitted') }}</th><th>{{ __('member_applications.applicant') }}</th><th>{{ __('Email') }}</th><th>{{ __('member_applications.membership_type') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>@foreach ($applications as $application)
                    <tr><td>{{ $application->registration_date?->format('Y-m-d') ?? '—' }}</td><td>{{ $application->full_name }}<small class="d-block text-muted">{{ $application->member_id }}</small></td><td>{{ $application->email }}</td><td>{{ $application->membershipType?->name ?? '—' }}</td><td><span class="badge text-bg-{{ $application->status === 'PENDING' ? 'warning' : ($application->status === 'ACTIVE' ? 'success' : 'secondary') }}">{{ __($application->status) }}</span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.member-applications.show', $application) }}">{{ __('View details') }}</a></td></tr>
                @endforeach</tbody>
            </table></div>
            <div class="p-3">{{ $applications->links() }}</div>
        @else
            <p class="text-center text-muted p-4 mb-0">{{ __('member_applications.no_results') }}</p>
        @endif
    </div>
</section>
@endsection
