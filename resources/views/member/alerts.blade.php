@extends('layouts.member')

@section('title', __('My Alerts'))

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h1 class="page-title">{{ __('My Alerts') }}</h1><p class="page-subtitle">{{ __('Notices related to your membership and borrowings') }}</p></div>
        <a href="{{ route('member.dashboard') }}" class="btn btn-outline-secondary">{{ __('Dashboard') }}</a>
    </div>

    @php($hasAlerts = $overdueBorrowings->isNotEmpty() || $dueSoonBorrowings->isNotEmpty() || $outstandingFines->isNotEmpty() || $outstandingCompensation->isNotEmpty() || $membershipExpiringSoon)
    @unless ($hasAlerts)<div class="alert alert-success">{{ __('You have no current alerts.') }}</div>@endunless

    @if ($membershipExpiringSoon)
        <div class="alert alert-warning">{{ __('Your membership expires on :date.', ['date' => $member->expiry_date->format('Y-m-d')]) }}</div>
    @endif
    @if ($overdueBorrowings->isNotEmpty())
        <section class="mb-4"><h2 class="h5">{{ __('Overdue books') }}</h2><ul class="list-group">@foreach ($overdueBorrowings as $borrowing)<li class="list-group-item d-flex justify-content-between"><span>{{ $borrowing->book?->title ?? 'Book' }}</span><span>{{ __('Due') }} {{ $borrowing->due_date?->format('Y-m-d') }}</span></li>@endforeach</ul></section>
    @endif
    @if ($dueSoonBorrowings->isNotEmpty())
        <section class="mb-4"><h2 class="h5">{{ __('Due soon') }}</h2><ul class="list-group">@foreach ($dueSoonBorrowings as $borrowing)<li class="list-group-item d-flex justify-content-between"><span>{{ $borrowing->book?->title ?? 'Book' }}</span><span>{{ __('Due') }} {{ $borrowing->due_date?->format('Y-m-d') }}</span></li>@endforeach</ul></section>
    @endif
    @if ($outstandingFines->isNotEmpty())
        <section class="mb-4"><h2 class="h5">{{ __('Outstanding fines') }}</h2><ul class="list-group">@foreach ($outstandingFines as $fine)<li class="list-group-item d-flex justify-content-between"><span>{{ __($fine->fine_type) }}</span><span>Rs. {{ number_format((float) $fine->amount, 2) }} · {{ __($fine->status) }}</span></li>@endforeach</ul></section>
    @endif
    @if ($outstandingCompensation->isNotEmpty())
        <section class="mb-4"><h2 class="h5">{{ __('Outstanding compensation') }}</h2><ul class="list-group">@foreach ($outstandingCompensation as $payment)<li class="list-group-item d-flex justify-content-between"><span>{{ __($payment->payment_type) }}</span><span>Rs. {{ number_format((float) $payment->amount, 2) }} · {{ __($payment->status) }}</span></li>@endforeach</ul></section>
    @endif
</div>
@endsection