@extends('layouts.admin')

@section('title', __('Fines'))

@section('content')
<div class="card">
    <div class="card-body">
        <h2 class="page-title">{{ __('Fines') }}</h2>
        <p class="page-subtitle">{{ __('Review outstanding charges, payments and waivers.') }}</p>
        @php($routePrefix = request()->routeIs('librarian.*') ? 'librarian' : 'admin')
        <div class="row g-3 mb-4">
            @foreach ([['Outstanding', $fineSummary['unpaid'], 'warning'], ['Paid', $fineSummary['paid'], 'success'], ['WAIVED', $fineSummary['waived'], 'secondary']] as [$label, $amount, $tone])
                <div class="col-sm-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ __($label) }} {{ __('total') }}</div><div class="fs-5 fw-semibold text-{{ $tone }}">Rs. {{ number_format((float) $amount, 2) }}</div></div></div></div>
            @endforeach
        </div>
        @unless (auth()->user()->isMainAdmin())
            <a href="{{ route($routePrefix . '.fines.create') }}" class="btn btn-primary mb-3">{{ __('Create Fine') }}</a>
        @endunless
        <form method="GET" class="row g-3 mb-4 align-items-end">
            <div class="col-md-7"><label class="form-label" for="fine-search">{{ __('Search fines') }}</label><input id="fine-search" type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Member, book or fine reason') }}"></div>
            <div class="col-md-3"><label class="form-label" for="fine-status">{{ __('Status') }}</label><select id="fine-status" name="status" class="form-select"><option value="">{{ __('All statuses') }}</option>@foreach (['UNPAID', 'PAID', 'WAIVED'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __($status) }}</option>@endforeach</select></div>
            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary">{{ __('Filter') }}</button></div>
        </form>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Member') }}</th>
                        <th>{{ __('Borrowing') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Issued') }}</th>
                        <th>{{ __('Paid') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($fines as $fine)
                        <tr>
                            <td>{{ $fine->member?->full_name ?? __('Member unavailable') }}</td>
                            <td>{{ $fine->borrowing_id ?? '—' }}</td>
                            <td>{{ __($fine->fine_type) }}</td>
                            <td>Rs. {{ number_format($fine->amount, 2) }}</td>
                            <td><span class="badge text-bg-{{ $fine->status === 'PAID' ? 'success' : ($fine->status === 'UNPAID' ? 'warning' : 'secondary') }}">{{ __($fine->status) }}</span></td>
                            <td>{{ $fine->issued_date?->format('Y-m-d') }}</td>
                            <td>{{ $fine->paid_date?->format('Y-m-d') ?? '—' }}</td>
                            <td>
                                <a href="{{ route($routePrefix . '.fines.show', $fine) }}" class="btn btn-sm btn-outline-primary">{{ __('View') }}</a>
                                @if ($fine->status === 'UNPAID' && ! auth()->user()->isMainAdmin())
                                    <form class="d-inline" method="POST" action="{{ route($routePrefix . '.fines.pay', $fine) }}" data-confirm="{{ __('Record payment for this fine?') }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success">{{ __('Pay') }}</button>
                                    </form>
                                    @if ($routePrefix === 'admin')
                                        <form class="d-inline" method="POST" action="{{ route('admin.fines.waive', $fine) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">{{ __('Waive') }}</button>
                                        </form>
                                    @endif
                                @elseif ($fine->status === 'UNPAID')
                                    <span class="text-muted">{{ __('Outstanding') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted">{{ __('No fines found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $fines->links() }}
    </div>
</div>
@endsection
