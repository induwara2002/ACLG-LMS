@extends('layouts.admin')

@section('title', __('Fine Details'))

@section('content')
@php($routePrefix = request()->routeIs('librarian.*') ? 'librarian' : 'admin')
<header class="report-page-header">
    <div><p class="section-kicker">{{ __('Fine record') }}</p><h1 class="page-title">{{ __('Fine #') }}{{ $fine->id }}</h1><p class="page-subtitle">{{ __('Fine reason, related circulation record and settlement status.') }}</p></div>
    <a href="{{ route($routePrefix . '.fines.index') }}" class="btn btn-outline-secondary">{{ __('Back to fines') }}</a>
</header>
<section class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
            <div><div class="text-muted small">{{ __('Fine amount') }}</div><div class="display-6 fw-semibold">Rs. {{ number_format((float) $fine->amount, 2) }}</div></div>
            <span class="badge text-bg-{{ $fine->status === 'PAID' ? 'success' : ($fine->status === 'UNPAID' ? 'warning' : 'secondary') }}">{{ __($fine->status) }}</span>
        </div>
        <dl class="row g-3 mb-0">
            <div class="col-md-6"><dt>{{ __('Member') }}</dt><dd>{{ $fine->member?->full_name ?? 'Member unavailable' }}</dd></div>
            <div class="col-md-6"><dt>{{ __('Member ID') }}</dt><dd>{{ $fine->member?->member_id ?? '—' }}</dd></div>
            <div class="col-md-6"><dt>{{ __('Reason') }}</dt><dd>{{ __($fine->fine_type) }}</dd></div>
            <div class="col-md-6"><dt>{{ __('Related borrowing') }}</dt><dd>@if ($fine->borrowing){{ $fine->borrowing->id }} · {{ $fine->borrowing->book?->title ?? 'Book unavailable' }} · {{ __('Accession Number') }} {{ $fine->borrowing->bookCopy?->accession_number ?? '—' }}@else — @endif</dd></div>
            <div class="col-md-6"><dt>{{ __('Issued date') }}</dt><dd>{{ $fine->issued_date?->format('Y-m-d') ?? '—' }}</dd></div>
            <div class="col-md-6"><dt>{{ __('Paid date') }}</dt><dd>{{ $fine->paid_date?->format('Y-m-d') ?? '—' }}</dd></div>
            <div class="col-12"><dt>{{ __('Description') }}</dt><dd>{{ $fine->description ?? '—' }}</dd></div>
        </dl>
    </div>
</section>

<section class="card"><div class="card-header bg-white"><h2 class="h5 mb-0">{{ __('Payment history') }}</h2></div><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Type') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
    @forelse ($fine->payments as $payment)
        <tr><td>#{{ $payment->id }}</td><td>{{ __($payment->payment_type) }}</td><td>Rs. {{ number_format((float) $payment->amount, 2) }}</td><td>{{ $payment->payment_date?->format('Y-m-d') ?? '—' }}</td><td><span class="status-badge">{{ __($payment->status) }}</span></td></tr>
    @empty
        <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No payments recorded for this fine.') }}</td></tr>
    @endforelse
</tbody></table></div></div></section>
@endsection
