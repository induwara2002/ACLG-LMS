@extends('layouts.member')

@section('title', __('My Damaged Books'))

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">{{ __('My Damaged Books') }}</h1>
            <p class="page-subtitle">{{ __('Damage reports for') }} {{ $member->member_id }}</p>
        </div>
        <a href="{{ route('member.dashboard') }}" class="btn btn-outline-secondary">{{ __('Back to dashboard') }}</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Book') }}</th>
                            <th>{{ __('Copy') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Damage date') }}</th>
                            <th>{{ __('Compensation') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($member->damageRecords as $damage)
                            <tr>
                                <td>{{ $damage->book->title ?? '—' }}</td>
                                <td>{{ $damage->bookCopy->accession_number ?? '—' }}</td>
                                <td>{{ __($damage->damage_type) }}</td>
                                <td>{{ $damage->damage_date?->format('Y-m-d') ?? '—' }}</td>
                                <td>Rs. {{ number_format((float) $damage->compensation_amount, 2) }}</td>
                                <td>{{ __($damage->payment_status) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-muted">{{ __('No damaged books recorded.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
