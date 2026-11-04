@extends('layouts.admin')

@section('title', __('Returns'))

@section('content')
<div class="card">
    <div class="card-body">
        <h2 class="page-title">{{ __('Returns') }}</h2>
        <p class="page-subtitle">{{ __('Review current loans, due dates and completed returns.') }}</p>
        <form method="GET" class="row g-3 mb-4 align-items-end">
            <div class="col-md-7"><label class="form-label" for="return-search">{{ __('Search borrowing, member or book') }}</label><input id="return-search" class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('Issue ID, member ID, book ID, title, ISBN or accession number') }}"></div>
            <div class="col-md-3"><label class="form-label" for="return-status">{{ __('Status') }}</label><select id="return-status" name="status" class="form-select"><option value="">{{ __('All statuses') }}</option>@foreach (['ISSUED', 'OVERDUE', 'RETURNED', 'LOST', 'DAMAGED'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __($status) }}</option>@endforeach</select></div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit">{{ __('Filter') }}</button></div>
        </form>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr><th>{{ __('Issue ID') }}</th><th>{{ __('Member') }}</th><th>{{ __('Membership') }}</th><th>{{ __('Book') }}</th><th>{{ __('Copy') }}</th><th>{{ __('Issue date') }}</th><th>{{ __('Due date') }}</th><th>{{ __('Return date') }}</th><th>{{ __('Status') }}</th><th>{{ __('Fine status') }}</th><th>{{ __('Action') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($borrowings as $borrowing)
                        @php($borrowingStatus = strtoupper((string) $borrowing->status))
                        @php($copyStatus = strtoupper((string) $borrowing->bookCopy?->status))
                        <tr>
                            <td>{{ $borrowing->id }}</td>
                            <td>{{ $borrowing->member?->full_name ?? 'Member unavailable' }}</td>
                            <td>{{ $borrowing->member?->membershipType?->name ?? '—' }}</td>
                            <td>{{ $borrowing->book?->title ?? 'Book unavailable' }}</td>
                            <td>{{ $borrowing->bookCopy?->accession_number ?? 'Copy unavailable' }}</td>
                            <td>{{ $borrowing->issue_at?->format('Y-m-d H:i:s') ?? $borrowing->issue_date?->format('Y-m-d') }}</td>
                            <td>{{ $borrowing->due_date?->format('Y-m-d') }}</td>
                            <td>
                                @if ($borrowing->returned_at)
                                    {{ $borrowing->returned_at->format('Y-m-d H:i:s') }}
                                @else
                                    {{ $borrowing->return_date?->format('Y-m-d') ?? '—' }}
                                @endif
                                @if ($borrowing->return_date && $borrowing->wasOverdue())
                                    <span class="badge text-bg-warning">{{ __('Returned overdue') }}</span>
                                @endif
                            </td>
                            <td><span class="badge text-bg-{{ $borrowing->effectiveStatus() === 'OVERDUE' ? 'danger' : ($borrowing->effectiveStatus() === 'ISSUED' ? 'primary' : 'secondary') }}">{{ __($borrowing->effectiveStatus()) }}</span></td>
                            <td>
                                @forelse ($borrowing->member?->fines ?? collect() as $fine)
                                    @if ((int) $fine->borrowing_id === (int) $borrowing->id)
                                        <div>{{ __($fine->status) }}: {{ number_format((float) $fine->amount, 2) }}</div>
                                    @endif
                                @empty
                                    <span class="text-muted">{{ __('None recorded') }}</span>
                                @endforelse
                            </td>
                            <td>
                                @if (auth()->user()->isMainAdmin())
                                    <span class="text-muted">{{ __('View only') }}</span>
                                @elseif ($borrowing->return_date !== null || $borrowingStatus === 'RETURNED')
                                    <span class="text-muted">{{ __('Completed') }}</span>
                                @elseif (in_array($borrowingStatus, ['ISSUED', 'OVERDUE'], true) && in_array($copyStatus, ['ISSUED', 'OVERDUE'], true))
                                    <form method="POST" action="{{ route($routePrefix . '.borrowings.return', $borrowing) }}" data-confirm="{{ __('Confirm return of this book?') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">{{ __('Return') }}</button>
                                    </form>
                                @else
                                    <span class="text-muted">{{ __('Unavailable') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-4">{{ __('No borrowing records found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $borrowings->links() }}
    </div>
</div>
@endsection
