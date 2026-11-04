@extends('layouts.admin')

@section('title', __('Borrowing Search'))

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">{{ __('Borrowing') }} {{ __('Search') }}</h1>
            <p class="text-muted mb-0">{{ __('Filter loan records across the catalogue.') }}</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.borrowings.search') }}" class="row g-3 align-items-end">
                @include('search._sabha-filter')
                <div class="col-md-3">
                    <label class="form-label">{{ __('Search') }}</label>
                    <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Borrowing ID, member, book, accession number') }}">
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
                    <label class="form-label">{{ __('Book') }}</label>
                    <select name="book_id" class="form-select">
                        <option value="">{{ __('All books') }}</option>
                        @foreach ($books as $book)
                            <option value="{{ $book->id }}" @selected(($filters['book_id'] ?? null) == $book->id)>{{ $book->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach (['ISSUED', 'OVERDUE', 'RETURNED', 'LOST', 'DAMAGED'] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? null) == $status)>{{ __($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Issue date from') }}</label>
                    <input type="date" name="issue_from" class="form-control" value="{{ $filters['issue_from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Issue date to') }}</label>
                    <input type="date" name="issue_to" class="form-control" value="{{ $filters['issue_to'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Due date from') }}</label>
                    <input type="date" name="due_from" class="form-control" value="{{ $filters['due_from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Due date to') }}</label>
                    <input type="date" name="due_to" class="form-control" value="{{ $filters['due_to'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Return date from') }}</label>
                    <input type="date" name="return_from" class="form-control" value="{{ $filters['return_from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Return date to') }}</label>
                    <input type="date" name="return_to" class="form-control" value="{{ $filters['return_to'] ?? '' }}">
                </div>
                <div class="col-md-2 d-grid">
                    <a href="{{ route('admin.borrowings.search') }}" class="btn btn-outline-secondary">{{ __('Clear') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if ($borrowings->count())
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('common.id') }}</th>
                                <th>{{ __('Member') }}</th>
                                <th>{{ __('Book') }}</th>
                                <th>{{ __('Copy') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Issue date') }}</th>
                                <th>{{ __('Due date') }}</th>
                                <th>{{ __('Return date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($borrowings as $borrowing)
                                <tr>
                                    <td>{{ $borrowing->id }}</td>
                                    <td>{{ $borrowing->member->full_name ?? '—' }}</td>
                                    <td>{{ $borrowing->book->title ?? '—' }}</td>
                                    <td>{{ $borrowing->bookCopy->accession_number ?? '—' }}</td>
                                    <td>{{ __($borrowing->status) }}</td>
                                    <td>{{ $borrowing->issue_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>{{ $borrowing->due_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>{{ $borrowing->return_date?->format('Y-m-d') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $borrowings->links() }}</div>
            @else
                <div class="p-4 text-center text-muted">{{ __('No borrowing records match the selected filters.') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
