@extends('layouts.admin')

@section('title', __('Members'))

@section('content')
@php($memberRoute = auth()->user()->hasRole('LIBRARIAN') ? 'librarian.members' : 'admin.members')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ __('Members') }}</h1>
        <p class="page-subtitle">{{ __('Manage registrations, membership status and member accounts.') }}</p>
    </div>
    @unless (auth()->user()->isMainAdmin())
        <a href="{{ route($memberRoute . '.create') }}" class="btn btn-primary">{{ __('Register Member') }}</a>
    @endunless
</div>

<div class="row g-3 mb-4">
    @foreach ([['Total Members', $summary['total'], 'primary'], ['Active', $summary['active'], 'success'], ['Expired', $summary['expired'], 'warning'], ['Suspended', $summary['suspended'], 'danger'], ['Children', $summary['children'], 'info'], ['Elders', $summary['elders'], 'secondary']] as [$label, $value, $color])
        <div class="col-6 col-lg-2"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ __($label) }}</div><div class="fs-4 fw-semibold text-{{ $color }}">{{ $value }}</div></div></div></div>
    @endforeach
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route($memberRoute . '.index') }}" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label" for="member-search">{{ __('Search members') }}</label>
                <input id="member-search" type="search" name="search" class="form-control" value="{{ $search ?? '' }}" placeholder="{{ __('ID, name, email, NIC or phone') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('Membership Type') }}</label>
                <select name="membership_type_id" class="form-select"><option value="">{{ __('All types') }}</option>@foreach ($membershipTypes as $type)<option value="{{ $type->id }}" @selected((string) $membershipTypeId === (string) $type->id)>{{ $type->name }}</option>@endforeach</select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('Status') }}</label>
                <select name="status" class="form-select"><option value="">{{ __('All statuses') }}</option>@foreach (['ACTIVE', 'EXPIRED', 'SUSPENDED'] as $option)<option value="{{ $option }}" @selected($status === $option)>{{ __($option) }}</option>@endforeach</select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">{{ __('Search') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        @if ($members->count())
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('Member ID') }}</th>
                            <th>{{ __('Full Name') }}</th>
                            <th>{{ __('Contact') }}</th>
                            <th>{{ __('Membership Type') }}</th>
                            <th>{{ __('Registration Date') }}</th>
                            <th>{{ __('Expiry Date') }}</th>
                            <th>{{ __('Borrowed') }}</th>
                            <th>{{ __('Unpaid Fines') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $member)
                            <tr>
                                <td>{{ $member->member_id }}</td>
                                <td>{{ $member->full_name }}</td>
                                <td>{{ $member->phone ?: ($member->email ?? '—') }}</td>
                                <td>{{ $member->membershipType->name ?? '—' }}</td>
                                <td>{{ $member->registration_date }}</td>
                                <td>{{ $member->expiry_date?->format('Y-m-d') ?? '—' }}</td>
                                <td>{{ $member->active_borrowings_count }}</td>
                                <td>Rs. {{ number_format((float) ($member->unpaid_fines_total ?? 0), 2) }}</td>
                                <td>
                                    @php($effectiveStatus = $member->effectiveStatus())
                                    <span class="badge text-bg-{{ $effectiveStatus === 'ACTIVE' ? 'success' : ($effectiveStatus === 'EXPIRED' ? 'warning' : 'secondary') }}">
                                        {{ __($effectiveStatus) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="table-row-actions">
                                        <a href="{{ route($memberRoute . '.show', $member) }}" class="btn btn-outline-info">{{ __('View') }}</a>
                                        @unless (auth()->user()->isMainAdmin())
                                            <a href="{{ route($memberRoute . '.edit', $member) }}" class="btn btn-outline-primary">{{ __('Edit') }}</a>
                                            @if ($member->status === 'SUSPENDED')
                                                <form action="{{ route($memberRoute . '.reactivate', $member) }}" method="POST">@csrf<button type="submit" class="btn btn-outline-success">{{ __('Activate') }}</button></form>
                                            @else
                                                <form action="{{ route($memberRoute . '.suspend', $member) }}" method="POST" onsubmit="return confirm(@js(__('Suspend this member?')));">@csrf<button type="submit" class="btn btn-outline-warning">{{ __('Suspend') }}</button></form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $members->links() }}
            </div>
        @else
            <div class="p-4 text-center text-muted">{{ __('No members found.') }}</div>
        @endif
    </div>
</div>
@endsection
