@extends('layouts.admin')

@section('title', __('Renewals'))

@section('content')
<div class="card">
    <div class="card-body">
        <h2 class="page-title">{{ __('Renewals') }}</h2>
        <p class="page-subtitle">{{ __('Review borrowing status and renewal history. Check eligibility and due dates before extending a loan.') }}</p>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr><th>{{ __('Member') }}</th><th>{{ __('Book') }}</th><th>{{ __('Issue date') }}</th><th>{{ __('Current due date') }}</th><th>{{ __('Proposed due date') }}</th><th>{{ __('Status') }}</th><th>{{ __('Renewals') }}</th><th>{{ __('Eligibility / action') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($borrowings as $borrowing)
                        <tr>
                            <td>{{ $borrowing->member?->full_name ?? 'Member unavailable' }}</td>
                            <td>{{ $borrowing->book?->title ?? 'Book unavailable' }}</td>
                            <td>{{ $borrowing->issue_date?->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $borrowing->due_date?->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $borrowing->due_date?->copy()->addDays($issuePeriodDays)->format('Y-m-d') ?? '—' }}</td>
                            <td><span class="badge text-bg-{{ $borrowing->effectiveStatus() === 'OVERDUE' ? 'danger' : ($borrowing->effectiveStatus() === 'ISSUED' ? 'primary' : 'secondary') }}">{{ __($borrowing->effectiveStatus()) }}</span></td>
                            <td>{{ $borrowing->renewal_count }} / {{ $maximumRenewals }}</td>
                            <td>
                                @php($canRenew = ! auth()->user()->isMainAdmin()
                                    && $borrowing->return_date === null
                                    && strtoupper((string) $borrowing->status) === 'ISSUED'
                                    && $borrowing->effectiveStatus() === 'ISSUED'
                                    && $borrowing->member?->effectiveStatus() === 'ACTIVE'
                                    && $borrowing->member?->user?->is_active
                                    && $borrowing->member?->fines->isEmpty()
                                    && $borrowing->renewal_count < $maximumRenewals
                                    && strtoupper((string) $borrowing->bookCopy?->status) === 'ISSUED')
                                @if ($canRenew)
                                    <form method="POST" action="{{ route($routePrefix . '.borrowings.renew', $borrowing) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Renew') }}</button>
                                    </form>
                                @elseif (auth()->user()->isMainAdmin())
                                    <span class="text-muted">{{ __('View only') }}</span>
                                @else
                                    @php($blockedReason = $borrowing->return_date !== null ? 'Already returned'
                                        : ($borrowing->effectiveStatus() === 'OVERDUE' ? 'Overdue loans cannot be renewed'
                                        : ($borrowing->member?->effectiveStatus() !== 'ACTIVE' ? 'Membership is inactive or expired'
                                        : ($borrowing->member?->fines->isNotEmpty() ? 'Outstanding fines must be resolved'
                                        : ($borrowing->renewal_count >= $maximumRenewals ? 'Maximum renewals reached'
                                        : 'Loan or copy is not currently eligible')))))
                                    <span class="small text-muted">{{ __($blockedReason) }}</span>
                                @endif
                                @if ($borrowing->renewals->isNotEmpty())
                                    <details class="mt-2"><summary class="small">{{ __('Renewal history') }}</summary>@foreach ($borrowing->renewals as $renewal)<div class="small text-muted">{{ $renewal->renewal_date?->format('Y-m-d') }}: {{ $renewal->old_due_date?->format('Y-m-d') }} → {{ $renewal->new_due_date?->format('Y-m-d') }}</div>@endforeach</details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No borrowing records found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $borrowings->links() }}
    </div>
</div>
@endsection
