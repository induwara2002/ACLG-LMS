@extends('layouts.admin')

@section('title', __('Payments'))

@section('content')
@php($routePrefix = request()->routeIs('librarian.*') ? 'librarian' : 'admin')
<header class="report-page-header">
    <div><h1 class="page-title">{{ __('Payments') }}</h1><p class="page-subtitle">{{ __('Review membership and fine payments recorded by the library.') }}</p></div>
</header>

<div class="row g-3 mb-4">
    <div class="col-sm-6"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ __('Fine payment matches') }}</div><div class="fs-4 fw-semibold">{{ $payments->total() }}</div><div class="small text-muted">{{ __('For the selected filters') }}</div></div></div></div>
    <div class="col-sm-6"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ __('Membership payment records') }}</div><div class="fs-4 fw-semibold">{{ $membershipPayments->count() }}</div><div class="small text-muted">{{ __('Latest matching records (maximum 100)') }}</div></div></div></div>
</div>

<section class="card mb-4">
    <div class="card-body">
        <h2 class="h5">{{ __('Filter payments') }}</h2>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4"><label class="form-label" for="payment-member">{{ __('Member') }}</label><select id="payment-member" name="member_id" class="form-select"><option value="">{{ __('All members') }}</option>@foreach ($members as $member)<option value="{{ $member->id }}" @selected((string) request('member_id') === (string) $member->id)>{{ $member->member_id }} - {{ $member->full_name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="payment-status">{{ __('Status') }}</label><select id="payment-status" name="status" class="form-select"><option value="">{{ __('All statuses') }}</option>@foreach (['PAID', 'PENDING', 'FAILED'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="payment-type-filter">{{ __('Type') }}</label><input id="payment-type-filter" name="payment_type" class="form-control" value="{{ request('payment_type') }}" placeholder="{{ __('Payment type') }}"></div>
            <div class="col-md-2"><label class="form-label" for="payment-from">{{ __('Date from') }}</label><input id="payment-from" type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
            <div class="col-md-2"><label class="form-label" for="payment-to">{{ __('Date to') }}</label><input id="payment-to" type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
            <div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-primary">{{ __('Apply filters') }}</button><a href="{{ route($routePrefix . '.payments.index') }}" class="btn btn-outline-secondary">{{ __('Clear') }}</a></div>
        </form>
    </div>
</section>

@unless (auth()->user()->isMainAdmin())
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5">{{ __('Record membership payment') }}</h2>
            <p class="text-muted small">{{ __('The configured fixed fee is applied when recording payment.') }}</p>
            <form method="POST" action="{{ route($routePrefix . '.payments.membership') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-4"><label class="form-label" for="record-member">{{ __('Member') }} <span class="required-mark">*</span></label><select id="record-member" name="member_id" class="form-select" required><option value="">{{ __('Select member') }}</option>@foreach ($members as $member)<option value="{{ $member->id }}" @selected(old('member_id') == $member->id)>{{ $member->member_id }} - {{ $member->full_name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label" for="record-payment-type">{{ __('Payment type') }} <span class="required-mark">*</span></label><select id="record-payment-type" name="payment_type" class="form-select" required><option value="REGISTRATION">{{ __('Registration') }}</option><option value="MONTHLY_MEMBERSHIP">{{ __('Monthly membership') }}</option><option value="LATE_RENEWAL">{{ __('Late renewal') }}</option></select></div>
                <div class="col-md-3"><label class="form-label" for="record-reference">{{ __('Reference') }}</label><input id="record-reference" name="reference" value="{{ old('reference') }}" class="form-control" placeholder="{{ __('Optional receipt/reference') }}"></div>
                <div class="col-md-2 d-grid"><button class="btn btn-primary" type="submit">{{ __('Record payment') }}</button></div>
                @error('payment')<div class="col-12 alert alert-danger mb-0">{{ $message }}</div>@enderror
            </form>
        </div>
    </section>
@endunless

<section class="card mb-4">
    <div class="card-body">
        <h2 class="h5">{{ __('Membership payments') }}</h2>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Member') }}</th><th>{{ __('Type') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reference') }}</th></tr></thead><tbody>
            @forelse ($membershipPayments as $payment)
                <tr><td>#{{ $payment->id }}</td><td>{{ $payment->member?->full_name ?? 'Member unavailable' }}</td><td>{{ __($payment->payment_type) }}</td><td>Rs. {{ number_format($payment->amount, 2) }}</td><td>{{ $payment->payment_date?->format('Y-m-d') ?? '—' }}</td><td><span class="status-badge">{{ __($payment->status) }}</span></td><td>{{ $payment->reference ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No membership payments match these filters.') }}</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
</section>

<section class="card">
    <div class="card-body">
        <h2 class="h5">{{ __('Fine payments') }}</h2>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Member') }}</th><th>{{ __('Type') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead><tbody>
            @forelse ($payments as $payment)
                <tr><td>#{{ $payment->id }}</td><td>{{ $payment->member?->full_name ?? 'Member unavailable' }}</td><td>{{ __($payment->payment_type) }}</td><td>Rs. {{ number_format($payment->amount, 2) }}</td><td>{{ $payment->payment_date?->format('Y-m-d') ?? '—' }}</td><td><span class="status-badge">{{ __($payment->status) }}</span></td><td><a href="{{ route($routePrefix . '.payments.show', $payment) }}" class="btn btn-sm btn-outline-primary">{{ __('View') }}</a></td></tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No fine payments match these filters.') }}</td></tr>
            @endforelse
        </tbody></table></div>
        {{ $payments->links() }}
    </div>
</section>
@endsection
