@extends('layouts.member')

@section('title', __('Outstanding Balance'))

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">{{ __('Outstanding Balance') }}</h1>
            <p class="page-subtitle">{{ __('Current unpaid balance for') }} {{ $member->member_id }}</p>
        </div>
        <a href="{{ route('member.payments') }}" class="btn btn-outline-secondary">{{ __('My payments') }}</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="display-6 mb-3">Rs. {{ number_format($member->fines->where('status', 'UNPAID')->sum('amount'), 2) }}</div>
            <p class="mb-0 text-muted">{{ __('Includes only unpaid fines. Paid and waived fines are excluded.') }}</p>
        </div>
    </div>
</div>
@endsection
