@extends('layouts.admin')

@section('title', __('Payment Details'))

@section('content')
@php($routePrefix = request()->routeIs('librarian.*') ? 'librarian' : 'admin')
<header class="report-page-header">
    <div><p class="section-kicker">{{ __('Payment record') }}</p><h1 class="page-title">{{ __('Payment') }} #{{ $payment->id }}</h1><p class="page-subtitle">{{ __('Recorded financial transaction details.') }}</p></div>
    <a href="{{ route($routePrefix . '.payments.index') }}" class="btn btn-outline-secondary">{{ __('Back to payments') }}</a>
</header>
<section class="card">
    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
            <div><div class="text-muted small">{{ __('Amount recorded') }}</div><div class="display-6 fw-semibold text-success">Rs. {{ number_format((float) $payment->amount, 2) }}</div></div>
            <span class="status-badge">{{ __($payment->status) }}</span>
        </div>
        <dl class="row g-3 mb-0">
            <div class="col-md-6"><dt>{{ __('Member') }}</dt><dd>{{ $payment->member?->full_name ?? 'Member unavailable' }}</dd></div>
            <div class="col-md-6"><dt>{{ __('Member ID') }}</dt><dd>{{ $payment->member?->member_id ?? '—' }}</dd></div>
            <div class="col-md-6"><dt>{{ __('Payment type') }}</dt><dd>{{ __($payment->payment_type) }}</dd></div>
            <div class="col-md-6"><dt>{{ __('Payment date') }}</dt><dd>{{ $payment->payment_date?->format('Y-m-d') ?? '—' }}</dd></div>
            <div class="col-md-6"><dt>{{ __('Reference / receipt') }}</dt><dd>{{ $payment->reference_number ?? $payment->reference ?? '—' }}</dd></div>
            <div class="col-md-6"><dt>{{ __('Related borrowing') }}</dt><dd>@if ($payment->borrowing){{ $payment->borrowing->id }} · {{ $payment->borrowing->book?->title ?? 'Book unavailable' }}@else — @endif</dd></div>
            @if ($payment->fine)
                <div class="col-md-6"><dt>{{ __('Related fine') }}</dt><dd>{{ __($payment->fine->fine_type) }} · {{ __('Fine #') }}{{ $payment->fine->id }}</dd></div>
            @endif
            <div class="col-12"><dt>{{ __('Description') }}</dt><dd>{{ $payment->description ?? '—' }}</dd></div>
        </dl>
    </div>
</section>
@endsection
