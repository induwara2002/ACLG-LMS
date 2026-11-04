@extends('layouts.admin')

@section('title', __('Member Details'))

@section('content')
@php($memberRoute = auth()->user()->hasRole('LIBRARIAN') ? 'librarian.members' : 'admin.members')
@php($memberStatus = $member->effectiveStatus())
<header class="member-profile-header report-page-header">
    <div><p class="section-kicker">{{ __('Member record') }}</p><h1 class="page-title">{{ $member->full_name }}</h1><p class="page-subtitle">{{ $member->member_id }} · {{ __('Membership Type:') }} {{ $member->membershipType?->name ?? __('Unavailable') }}</p><span class="badge text-bg-{{ $memberStatus === 'ACTIVE' ? 'success' : ($memberStatus === 'EXPIRED' ? 'warning' : 'secondary') }}">{{ __($memberStatus) }}</span></div>
    <div class="d-flex flex-wrap gap-2">@unless (auth()->user()->isMainAdmin())<a href="{{ route($memberRoute . '.edit', $member) }}" class="btn btn-primary">{{ __('Edit member') }}</a><form method="POST" action="{{ route($memberRoute . '.renew', $member) }}" data-confirm="{{ __('Renew this membership?') }}">@csrf<button class="btn btn-outline-success" type="submit">{{ __('Renew membership') }}</button></form>@endunless<a href="{{ route($memberRoute . '.index') }}" class="btn btn-outline-secondary">{{ __('Back to members') }}</a></div>
</header>

<section class="card mb-4">
    <div class="card-header bg-white"><h2 class="h5 mb-0">{{ __('Membership and contact') }}</h2></div>
    <div class="card-body"><dl class="row g-3 mb-0">
        <div class="col-md-6"><dt>{{ __('Member ID') }}</dt><dd>{{ $member->member_id }}</dd></div>
        <div class="col-md-6"><dt>{{ __('NIC') }}</dt><dd>{{ $member->nic ?? '—' }}</dd></div>
        <div class="col-md-6"><dt>{{ __('Phone') }}</dt><dd>{{ $member->phone ?: '—' }}</dd></div>
        <div class="col-md-6"><dt>{{ __('Email') }}</dt><dd>{{ $member->email ?? '—' }}</dd></div>
        <div class="col-md-6"><dt>{{ __('Registration date') }}</dt><dd>{{ $member->registration_date?->format('Y-m-d') ?? '—' }}</dd></div>
        <div class="col-md-6"><dt>{{ __('Membership starts') }}</dt><dd>{{ $member->membership_start_date?->format('Y-m-d') ?? '—' }}</dd></div>
        <div class="col-md-6"><dt>{{ __('Next renewal') }}</dt><dd>{{ $member->next_renewal_date?->format('Y-m-d') ?? '—' }}</dd></div>
        <div class="col-md-6"><dt>{{ __('Expiry date') }}</dt><dd>{{ $member->expiry_date?->format('Y-m-d') ?? '—' }}</dd></div>
        <div class="col-md-6"><dt>{{ __('Registration fee') }}</dt><dd>Rs. {{ number_format((float) $member->registration_fee, 2) }}</dd></div>
        <div class="col-12"><dt>{{ __('Address') }}</dt><dd>{{ $member->address ?? '—' }}</dd></div>
    </dl></div>
</section>

<section class="card mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">{{ __('Current borrowing and history') }}</h2></div><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>{{ __('Book') }}</th><th>{{ __('Copy') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Due') }}</th><th>{{ __('Returned') }}</th><th>{{ __('Status') }}</th><th>{{ __('Renewals') }}</th></tr></thead><tbody>
    @forelse ($member->borrowings as $borrowing)
        <tr><td>{{ $borrowing->book?->title ?? 'Book unavailable' }}</td><td>{{ $borrowing->bookCopy?->accession_number ?? '—' }}</td><td>{{ $borrowing->issue_date?->format('Y-m-d') ?? '—' }}</td><td>{{ $borrowing->due_date?->format('Y-m-d') ?? '—' }}</td><td>{{ $borrowing->return_date?->format('Y-m-d') ?? '—' }}</td><td><span class="status-badge">{{ __($borrowing->effectiveStatus()) }}</span></td><td>{{ $borrowing->renewal_count }}</td></tr>
    @empty
        <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No borrowing history recorded.') }}</td></tr>
    @endforelse
</tbody></table></div></div></section>

<section class="card mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">{{ __('Fines') }}</h2></div><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>{{ __('Reason') }}</th><th>{{ __('Borrowing') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Paid') }}</th></tr></thead><tbody>
    @forelse ($member->fines as $fine)
        <tr><td>{{ __($fine->fine_type) }}</td><td>{{ $fine->borrowing_id ?? '—' }}</td><td>{{ $fine->issued_date?->format('Y-m-d') ?? '—' }}</td><td>Rs. {{ number_format((float) $fine->amount, 2) }}</td><td><span class="status-badge">{{ __($fine->status) }}</span></td><td>{{ $fine->paid_date?->format('Y-m-d') ?? '—' }}</td></tr>
    @empty
        <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No fines recorded.') }}</td></tr>
    @endforelse
</tbody></table></div></div></section>

<section class="card"><div class="card-header bg-white"><h2 class="h5 mb-0">{{ __('Membership payments') }}</h2></div><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Type') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reference') }}</th></tr></thead><tbody>
    @forelse ($member->membershipPayments as $payment)
        <tr><td>#{{ $payment->id }}</td><td>{{ __($payment->payment_type) }}</td><td>Rs. {{ number_format((float) $payment->amount, 2) }}</td><td>{{ $payment->payment_date?->format('Y-m-d') ?? '—' }}</td><td><span class="status-badge">{{ __($payment->status) }}</span></td><td>{{ $payment->reference ?? '—' }}</td></tr>
    @empty
        <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No membership payments recorded.') }}</td></tr>
    @endforelse
</tbody></table></div></div></section>

<section class="card mt-4"><div class="card-header bg-white"><h2 class="h5 mb-0">{{ __('Fine payments') }}</h2></div><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Type') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
    @forelse ($member->payments as $payment)
        <tr><td>#{{ $payment->id }}</td><td>{{ __($payment->payment_type) }}</td><td>Rs. {{ number_format((float) $payment->amount, 2) }}</td><td>{{ $payment->payment_date?->format('Y-m-d') ?? '—' }}</td><td><span class="status-badge">{{ __($payment->status) }}</span></td></tr>
    @empty
        <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No fine payments recorded.') }}</td></tr>
    @endforelse
</tbody></table></div></div></section>
@endsection
