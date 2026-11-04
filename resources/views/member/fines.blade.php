@extends('layouts.member')

@section('title', __('My Fines'))

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">{{ __('My Fines') }}</h1>
            <p class="page-subtitle">{{ __('Fines and balances for') }} {{ $member->member_id }}</p>
        </div>
        <a href="{{ route('member.dashboard') }}" class="btn btn-outline-secondary">{{ __('Back to dashboard') }}</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('common.id') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Issued') }}</th>
                            <th>{{ __('Paid') }}</th>
                            <th>{{ __('Description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($member->fines as $fine)
                            <tr>
                                <td>{{ $fine->id }}</td>
                                <td>{{ __($fine->fine_type) }}</td>
                                <td>Rs. {{ number_format($fine->amount, 2) }}</td>
                                <td>{{ __($fine->status) }}</td>
                                <td>{{ $fine->issued_date?->format('Y-m-d') ?? '—' }}</td>
                                <td>{{ $fine->paid_date?->format('Y-m-d') ?? '—' }}</td>
                                <td>{{ $fine->description ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted">{{ __('No fines recorded.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
