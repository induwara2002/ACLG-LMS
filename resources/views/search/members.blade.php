@extends('layouts.admin')

@section('title', __('Member Search'))

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">{{ __('Member') }} {{ __('Search') }}</h1>
            <p class="text-muted mb-0">{{ __('Search and filter member records') }}.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.members.search') }}" class="row g-3 align-items-end">
                @include('search._sabha-filter')
                <div class="col-md-4">
                    <label class="form-label">{{ __('Search') }}</label>
                    <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Member ID, name, email, NIC, phone') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Membership type') }}</label>
                    <select name="membership_type_id" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($membershipTypes as $type)
                            <option value="{{ $type->id }}" @selected(($filters['membership_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach (['ACTIVE', 'EXPIRED', 'SUSPENDED'] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? null) == $status)>{{ __($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Registration date from') }}</label>
                    <input type="date" name="registration_from" class="form-control" value="{{ $filters['registration_from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Registration date to') }}</label>
                    <input type="date" name="registration_to" class="form-control" value="{{ $filters['registration_to'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Expiry date from') }}</label>
                    <input type="date" name="expiry_from" class="form-control" value="{{ $filters['expiry_from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Expiry date to') }}</label>
                    <input type="date" name="expiry_to" class="form-control" value="{{ $filters['expiry_to'] ?? '' }}">
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
                </div>
                <div class="col-md-2 d-grid">
                    <a href="{{ route('admin.members.search') }}" class="btn btn-outline-secondary">{{ __('Clear') }}</a>
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
                                <th>{{ __('Email') }}</th>
                                <th>{{ __('NIC') }}</th>
                                <th>{{ __('Phone') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Expiry date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($members as $member)
                                <tr>
                                    <td>{{ $member->member_id }}</td>
                                    <td>{{ $member->full_name }}</td>
                                    <td>{{ $member->email }}</td>
                                    <td>{{ $member->nic }}</td>
                                    <td>{{ $member->phone }}</td>
                                    <td>{{ __($member->status) }}</td>
                                    <td>{{ $member->expiry_date?->format('Y-m-d') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $members->links() }}</div>
            @else
                <div class="p-4 text-center text-muted">{{ __('No members match the selected filters.') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
