@extends('layouts.admin')

@section('title', __('member_applications.application_details'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><p class="section-kicker">{{ $application->pradeshiyaSabha?->name }}</p><h1 class="page-title">{{ $application->full_name }}</h1><p class="page-subtitle">{{ __('member_applications.submitted') }} {{ $application->registration_date?->format('Y-m-d') ?? '—' }} · {{ $application->member_id }}</p></div>
    <a href="{{ route('admin.member-applications.index') }}" class="btn btn-outline-secondary">{{ __('member_applications.back_to_applications') }}</a>
</div>
<section class="card mb-4">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2"><h2 class="h5 mb-0">{{ __('member_applications.application_details') }}</h2><span class="badge text-bg-{{ $application->status === 'PENDING' ? 'warning' : ($application->status === 'ACTIVE' ? 'success' : 'secondary') }}">{{ __($application->status) }}</span></div>
    <div class="card-body"><dl class="row g-3 mb-0">
        <div class="col-md-6"><dt>{{ __('member_applications.applicant') }}</dt><dd>{{ $application->full_name }}</dd></div>
        <div class="col-md-6"><dt>{{ __('Email') }}</dt><dd>{{ $application->email }}</dd></div>
        <div class="col-md-6"><dt>{{ __('NIC') }}</dt><dd>{{ $application->nic }}</dd></div>
        <div class="col-md-6"><dt>{{ __('Phone') }}</dt><dd>{{ $application->phone }}</dd></div>
        <div class="col-md-6"><dt>{{ __('member_applications.membership_type') }}</dt><dd>{{ $application->membershipType?->name ?? '—' }}</dd></div>
        <div class="col-md-6"><dt>{{ __('member_applications.registered_sabha') }}</dt><dd>{{ $application->pradeshiyaSabha?->name ?? '—' }}</dd></div>
        <div class="col-md-6"><dt>{{ __('member_applications.submitted') }}</dt><dd>{{ $application->registration_date?->format('Y-m-d') ?? '—' }}</dd></div>
        <div class="col-12"><dt>{{ __('Address') }}</dt><dd>{{ $application->address }}</dd></div>
        @if ($application->status === 'ACTIVE')<div class="col-md-6"><dt>{{ __('member_applications.approved_by') }}</dt><dd>{{ $application->approvedBy?->name ?? '—' }} · {{ $application->approved_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>@endif
        @if ($application->status === 'REJECTED')<div class="col-md-6"><dt>{{ __('member_applications.rejected_by') }}</dt><dd>{{ $application->rejectedBy?->name ?? '—' }} · {{ $application->rejected_at?->format('Y-m-d H:i') ?? '—' }}</dd></div><div class="col-12"><dt>{{ __('member_applications.rejection_reason') }}</dt><dd>{{ $application->rejection_reason ?? '—' }}</dd></div>@endif
    </dl></div>
</section>
@if ($application->status === 'PENDING')
    <div class="row g-4">
        <div class="col-md-6"><section class="card h-100"><div class="card-body"><h2 class="h5">{{ __('member_applications.approve_title') }}</h2><p>{{ __('member_applications.approve_prompt') }}</p><form method="POST" action="{{ route('admin.member-applications.approve', $application) }}" data-confirm="{{ __('member_applications.confirm_approve') }}">@csrf<button class="btn btn-success" type="submit">{{ __('member_applications.approve') }}</button></form></div></section></div>
        <div class="col-md-6"><section class="card h-100"><div class="card-body"><h2 class="h5">{{ __('member_applications.reject_title') }}</h2><form method="POST" action="{{ route('admin.member-applications.reject', $application) }}">@csrf<label for="rejection_reason" class="form-label">{{ __('member_applications.rejection_reason') }}</label><textarea id="rejection_reason" name="rejection_reason" class="form-control @error('rejection_reason') is-invalid @enderror" rows="4" minlength="5" maxlength="1000" required>{{ old('rejection_reason') }}</textarea>@error('rejection_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror<button class="btn btn-outline-danger mt-3" type="submit">{{ __('member_applications.reject') }}</button></form></div></section></div>
    </div>
@endif
@endsection
