@props(['borrowings'])

@if ($borrowings->isEmpty())
    <div class="ps-table-empty">
        <span aria-hidden="true">⌁</span>
        <strong>{{ __('No borrowing activity yet') }}</strong>
        <p>{{ __('New issues and returns will appear here.') }}</p>
        <a href="{{ route('admin.issue-books') }}">{{ __('Issue a book') }}</a>
    </div>
@else
    <div class="table-responsive">
        <table class="table ps-activity-table align-middle mb-0">
            <thead><tr><th scope="col">{{ __('Member') }}</th><th scope="col">{{ __('Book') }}</th><th scope="col">{{ __('Action') }}</th><th scope="col">{{ __('Date') }}</th><th scope="col">{{ __('Status') }}</th></tr></thead>
            <tbody>
                @foreach ($borrowings as $borrowing)
                    @php
                        $activityStatus = $borrowing->effectiveStatus();
                        $activityAction = match ($activityStatus) {
                            'RETURNED' => 'Returned',
                            'OVERDUE' => 'Overdue',
                            'LOST' => 'Lost',
                            default => 'Issued',
                        };
                    @endphp
                    <tr>
                        <td><span class="ps-table-primary">{{ $borrowing->member?->full_name ?? 'Member unavailable' }}</span></td>
                        <td>{{ $borrowing->book?->title ?? 'Book unavailable' }}</td>
                        <td>{{ $activityAction }}</td>
                        <td>{{ $borrowing->issue_date?->format('Y-m-d') ?? '—' }}</td>
                        <td><x-admin.status-badge :status="$activityStatus" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
