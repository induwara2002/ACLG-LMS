@extends('layouts.member')

@section('title', __('My Lost Books'))

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">{{ __('My Lost Books') }}</h1>
            <p class="page-subtitle">{{ __('Lost book reports for') }} {{ $member->member_id }}</p>
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
                            <th>{{ __('Lost date') }}</th>
                            <th>{{ __('Compensation') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($member->lostBooks as $lostBook)
                            <tr>
                                <td>{{ $lostBook->book->title ?? '—' }}</td>
                                <td>{{ $lostBook->bookCopy->accession_number ?? '—' }}</td>
                                <td>{{ $lostBook->lost_date?->format('Y-m-d') ?? '—' }}</td>
                                <td>Rs. {{ number_format((float) $lostBook->compensation_amount, 2) }}</td>
                                <td>{{ __($lostBook->payment_status) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted">{{ __('No lost books recorded.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
