@extends('layouts.admin')

@section('title', __('Report Lost Book'))

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">{{ __('Report Lost') }} {{ __('Book') }}</h1>
            <p class="text-muted mb-0">{{ __('Log a lost copy and set compensation.') }}</p>
        </div>
        <a href="{{ route(request()->routeIs('librarian.*') ? 'librarian.lost-damaged.index' : 'admin.lost-damaged.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route(request()->routeIs('librarian.*') ? 'librarian.lost-books.store' : 'admin.lost-books.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Member') }}</label>
                        <select name="member_id" class="form-select" required>
                            <option value="">{{ __('Select member') }}</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" {{ old('member_id') == $member->id ? 'selected' : '' }}>{{ $member->full_name }} ({{ $member->member_id }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Borrowing') }} ID</label>
                        <input type="number" name="borrowing_id" class="form-control" value="{{ old('borrowing_id') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Book') }} {{ __('Copy') }} ID</label>
                        <input type="number" name="book_copy_id" class="form-control" value="{{ old('book_copy_id') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Lost date') }}</label>
                        <input type="date" name="lost_date" class="form-control" value="{{ old('lost_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Compensation') }} {{ __('Amount') }}</label>
                        <input type="number" step="0.01" min="0.01" name="compensation_amount" class="form-control" value="{{ old('compensation_amount') }}" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">{{ __('Notes') }}</label>
                        <textarea name="notes" rows="4" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">{{ __('Save report') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
