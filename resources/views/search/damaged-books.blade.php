@extends('layouts.admin')

@section('title', __('Damaged Book Search'))

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">{{ __('Damaged Book Search') }}</h1>
            <p class="text-muted mb-0">{{ __('Search and filter damaged-copy compensation records.') }}</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.damaged-books.search') }}" class="row g-3 align-items-end">
                @include('search._sabha-filter')
                <div class="col-md-3">
                    <label class="form-label">{{ __('Search') }}</label>
                    <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Member, title, accession number') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Member') }}</label>
                    <select name="member_id" class="form-select">
                        <option value="">{{ __('All members') }}</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}" @selected(($filters['member_id'] ?? null) == $member->id)>{{ $member->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Borrowing') }} ID</label>
                    <input type="number" name="borrowing_id" class="form-control" value="{{ $filters['borrowing_id'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Damage type') }}</label>
                    <select name="damage_type" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        <option value="MINOR" @selected(($filters['damage_type'] ?? null) == 'MINOR')>{{ __('MINOR') }}</option>
                        <option value="MAJOR" @selected(($filters['damage_type'] ?? null) == 'MAJOR')>{{ __('MAJOR') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Payment status') }}</label>
                    <select name="payment_status" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        <option value="UNPAID" @selected(($filters['payment_status'] ?? null) == 'UNPAID')>{{ __('UNPAID') }}</option>
                        <option value="PAID" @selected(($filters['payment_status'] ?? null) == 'PAID')>{{ __('PAID') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Damage date from') }}</label>
                    <input type="date" name="damage_from" class="form-control" value="{{ $filters['damage_from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Damage date to') }}</label>
                    <input type="date" name="damage_to" class="form-control" value="{{ $filters['damage_to'] ?? '' }}">
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
                </div>
                <div class="col-md-2 d-grid">
                    <a href="{{ route('admin.damaged-books.search') }}" class="btn btn-outline-secondary">{{ __('Clear') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if ($damageRecords->count())
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('common.id') }}</th>
                                <th>{{ __('Member') }}</th>
                                <th>{{ __('Book') }}</th>
                                <th>{{ __('Accession Number') }}</th>
                                <th>{{ __('Damage type') }}</th>
                                <th>{{ __('Compensation') }}</th>
                                <th>{{ __('Payment status') }}</th>
                                <th>{{ __('Damage date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($damageRecords as $damage)
                                <tr>
                                    <td>{{ $damage->id }}</td>
                                    <td>{{ $damage->member->full_name ?? '—' }}</td>
                                    <td>{{ $damage->book->title ?? '—' }}</td>
                                    <td>{{ $damage->bookCopy->accession_number ?? '—' }}</td>
                                    <td>{{ __($damage->damage_type) }}</td>
                                    <td>{{ $damage->compensation_amount ? '$'.$damage->compensation_amount : '—' }}</td>
                                    <td>{{ __($damage->payment_status) }}</td>
                                    <td>{{ $damage->damage_date?->format('Y-m-d') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $damageRecords->links() }}</div>
            @else
                <div class="p-4 text-center text-muted">{{ __('No damaged-book records match the selected filters.') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
