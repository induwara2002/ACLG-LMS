@extends('layouts.admin')

@section('title', __('Lost / Damaged Books'))

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">{{ __('Lost / Damaged Books') }}</h1>
            <p class="text-muted mb-0">{{ __('Track compensation, payment status, and copy records.') }}</p>
        </div>
        @unless (auth()->user()->isMainAdmin())
            <div class="d-flex gap-2">
                <a href="{{ route(request()->routeIs('librarian.*') ? 'librarian.lost-damaged.create-lost' : 'admin.lost-damaged.create-lost') }}" class="btn btn-primary">{{ __('Report Lost') }}</a>
                <a href="{{ route(request()->routeIs('librarian.*') ? 'librarian.lost-damaged.create-damaged' : 'admin.lost-damaged.create-damaged') }}" class="btn btn-outline-primary">{{ __('Report Damage') }}</a>
            </div>
        @endunless
    </div>

    <div class="row g-4">
        <div class="col-xl-6" id="lost-books">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">{{ __('Lost books') }}</h2>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Incident date') }}</th>
                                    <th>{{ __('Member') }}</th>
                                    <th>{{ __('Book') }}</th>
                                    <th>{{ __('Accession Number') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Comp.') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lostBooks as $lostBook)
                                    <tr>
                                        <td>{{ $lostBook->lost_date?->format('Y-m-d') ?? '—' }}</td>
                                        <td>{{ $lostBook->member->full_name ?? '—' }}</td>
                                        <td>{{ $lostBook->book->title ?? '—' }}</td>
                                        <td>{{ $lostBook->bookCopy->accession_number ?? '—' }}</td>
                                        <td><span class="status-badge">{{ __($lostBook->status) }}</span></td>
                                        <td>Rs. {{ number_format((float) $lostBook->compensation_amount, 2) }}</td>
                                        <td>
                                            @if ($lostBook->payment_status !== 'PAID' && ! auth()->user()->isMainAdmin())
                                                <form method="POST" action="{{ route((request()->routeIs('librarian.*') ? 'librarian' : 'admin') . '.lost-books.pay', $lostBook) }}" data-confirm="{{ __('Record payment for this lost-book compensation?') }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success">{{ __('Pay') }}</button>
                                                </form>
                                            @elseif ($lostBook->payment_status === 'PAID')
                                                <span class="badge text-bg-success">{{ __('Paid') }}</span>
                                            @else
                                                <span class="text-muted">{{ __('Outstanding') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">{{ __('No lost books recorded.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6" id="damage-records">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">{{ __('Damaged books') }}</h2>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Incident date') }}</th>
                                    <th>{{ __('Member') }}</th>
                                    <th>{{ __('Book') }}</th>
                                    <th>{{ __('Accession Number') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Comp.') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($damageRecords as $damage)
                                    <tr>
                                        <td>{{ $damage->damage_date?->format('Y-m-d') ?? '—' }}</td>
                                        <td>{{ $damage->member->full_name ?? '—' }}</td>
                                        <td>{{ $damage->book->title ?? '—' }}</td>
                                        <td>{{ $damage->bookCopy->accession_number ?? '—' }}</td>
                                        <td>{{ __($damage->damage_type) }}</td>
                                        <td>Rs. {{ number_format((float) $damage->compensation_amount, 2) }}</td>
                                        <td>
                                            @if ($damage->payment_status !== 'PAID' && ! auth()->user()->isMainAdmin())
                                                <form method="POST" action="{{ route((request()->routeIs('librarian.*') ? 'librarian' : 'admin') . '.damaged-books.pay', $damage) }}" data-confirm="{{ __('Record payment for this damage compensation?') }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success">{{ __('Pay') }}</button>
                                                </form>
                                            @elseif ($damage->payment_status === 'PAID')
                                                <span class="badge text-bg-success">{{ __('Paid') }}</span>
                                            @else
                                                <span class="text-muted">{{ __('Outstanding') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">{{ __('No damaged books recorded.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
