@extends('layouts.admin')

@section('title', __('Issue Books'))

@section('content')
@php($routePrefix = request()->routeIs('librarian.*') ? 'librarian' : 'admin')
<div class="circulation-page">
    <header class="circulation-page-heading">
        <div>
            <p class="section-kicker">{{ __('Circulation desk') }}</p>
            <h1 class="page-title">{{ __('Issue a Book') }}</h1>
            <p class="page-subtitle">{{ __('Select and verify a member and an available physical copy before recording a new loan.') }}</p>
        </div>
        <a href="{{ route($routePrefix . '.borrowings.index') }}" class="btn btn-outline-primary">{{ __('View Borrowings') }}</a>
    </header>
    <div class="card">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-6">
                    <form class="issue-lookup-form" data-member-lookup-form>
                        <label class="form-label" for="issue-member-search">{{ __('Member ID') }} <span class="required-mark">*</span></label>
                        <div class="input-group">
                            <input id="issue-member-search" class="form-control" type="search" autocomplete="off" required maxlength="64" placeholder="{{ __('Enter member ID') }}">
                            <button class="btn btn-outline-primary" type="submit">{{ __('Search Member') }}</button>
                        </div>
                    </form>
                    <div class="issue-lookup-result mt-3" data-member-result aria-live="polite">{{ __('Search for a member to verify membership eligibility.') }}</div>
                </div>
                <div class="col-lg-6">
                    <form class="issue-lookup-form" data-book-lookup-form>
                        <label class="form-label" for="issue-book-search">{{ __('Book ID, ISBN or title') }} <span class="required-mark">*</span></label>
                        <div class="input-group">
                            <input id="issue-book-search" class="form-control" type="search" autocomplete="off" required minlength="2" maxlength="100" placeholder="{{ __('Search by book ID, ISBN or title') }}">
                            <button class="btn btn-outline-primary" type="submit">{{ __('Search Books') }}</button>
                        </div>
                    </form>
                    <div class="issue-book-results mt-3" data-book-results aria-live="polite">{{ __('Search for a title to review its physical copy availability.') }}</div>
                </div>
            </div>

            <hr class="my-4">

            <form method="POST" action="{{ route($routePrefix . '.borrowings.store') }}" class="row g-3 issue-book-form" data-issue-form>
                @csrf
                <input type="hidden" name="member_id" data-member-id>
                <input type="hidden" name="book_id" data-book-id>
                <div class="col-md-6">
                    <label class="form-label" for="issue-date">{{ __('Issue date and time') }}</label>
                    <input id="issue-date" type="text" class="form-control" value="{{ now()->format('Y-m-d H:i:s') }}" readonly>
                    <div class="form-text">{{ __('The server records the issue date and time when the loan is saved.') }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="issue-due-date">{{ __('Due date') }}</label>
                    <input id="issue-due-date" type="date" class="form-control" value="{{ today()->addDays($issuePeriodDays)->toDateString() }}" readonly>
                    <div class="form-text">{{ __('Uses the configured :days-day issue period.', ['days' => $issuePeriodDays]) }}</div>
                </div>
                <div class="col-12">
                    @error('issue')<div class="alert alert-danger mb-0">{{ $message }}</div>@enderror
                    <div class="issue-confirmation">
                        <strong>{{ __('Confirm issue') }}</strong>
                        <span>{{ __('Eligibility, library access, configured due date and physical copy availability are checked again on the server.') }}</span>
                    </div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    @unless (auth()->user()->isMainAdmin())
                        <button type="submit" class="btn btn-primary" data-issue-submit disabled>{{ __('Issue Book') }}</button>
                    @else
                        <span class="text-muted align-self-center">{{ __('Super Admin access is read-only.') }}</span>
                    @endunless
                    <a href="{{ route($routePrefix . '.borrowings.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const memberForm = document.querySelector('[data-member-lookup-form]');
        const bookForm = document.querySelector('[data-book-lookup-form]');
        const memberResult = document.querySelector('[data-member-result]');
        const bookResults = document.querySelector('[data-book-results]');
        const memberId = document.querySelector('[data-member-id]');
        const bookId = document.querySelector('[data-book-id]');
        const issueButton = document.querySelector('[data-issue-submit]');
        let memberEligible = false;
        let bookAvailable = false;
        const labels = {
            active: @js(__('ACTIVE')),
            expired: @js(__('EXPIRED')),
            suspended: @js(__('SUSPENDED')),
            inactive: @js(__('INACTIVE')),
            expiry: @js(__('Membership expiry')),
            membership: @js(__('Membership')),
            copies: @js(__('available copies')),
            noResults: @js(__('No matching books were found.')),
            lookupError: @js(__('The record could not be loaded. Please check the search and try again.')),
            loading: @js(__('Searching...'))
        };

        const updateIssueButton = function () {
            if (issueButton) issueButton.disabled = !(memberEligible && bookAvailable);
        };
        const setText = function (element, value) {
            element.textContent = value ?? '—';
        };
        const getJson = async function (url) {
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || labels.lookupError);
            return data;
        };

        memberForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            memberId.value = '';
            memberEligible = false;
            updateIssueButton();
            memberResult.textContent = labels.loading;
            const query = new URLSearchParams({member_id: document.querySelector('#issue-member-search').value.trim()});
            try {
                const member = await getJson(@js(route($routePrefix . '.members.lookup')) + '?' + query.toString());
                const status = member.status;
                memberEligible = member.eligible;
                memberId.value = member.id;
                memberResult.replaceChildren();
                const name = document.createElement('strong');
                name.textContent = member.name;
                const details = document.createElement('span');
                details.textContent = member.member_id + ' · ' + labels.membership + ': ' + (member.membership_type || '—')
                    + ' · ' + (labels[status.toLowerCase()] || status)
                    + ' · ' + labels.expiry + ': ' + (member.expiry_date || '—');
                memberResult.append(name, details);
                memberResult.classList.toggle('is-warning', !memberEligible);
            } catch (error) {
                memberResult.textContent = error.message;
                memberResult.classList.add('is-warning');
            }
            updateIssueButton();
        });

        bookForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            bookId.value = '';
            bookAvailable = false;
            updateIssueButton();
            bookResults.textContent = labels.loading;
            const query = new URLSearchParams({search: document.querySelector('#issue-book-search').value.trim()});
            try {
                const books = await getJson(@js(route($routePrefix . '.books.lookup')) + '?' + query.toString());
                bookResults.replaceChildren();
                bookResults.classList.remove('is-warning');
                if (books.length === 0) {
                    bookResults.textContent = labels.noResults;
                    return;
                }
                books.forEach(function (book) {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'issue-book-result';
                    option.disabled = !book.available;
                    const title = document.createElement('strong');
                    title.textContent = book.title;
                    const details = document.createElement('span');
                    details.textContent = book.book_id + ' · ISBN ' + (book.isbn || '—') + ' · '
                        + (book.author || '—') + ' · ' + book.available_copies + ' ' + labels.copies
                        + ' / ' + book.total_copies;
                    option.append(title, details);
                    option.addEventListener('click', function () {
                        bookId.value = book.id;
                        bookAvailable = book.available;
                        bookResults.querySelectorAll('.issue-book-result').forEach(item => item.classList.remove('is-selected'));
                        option.classList.add('is-selected');
                        updateIssueButton();
                    });
                    bookResults.append(option);
                });
            } catch (error) {
                bookResults.textContent = error.message;
                bookResults.classList.add('is-warning');
            }
        });
    });
</script>
@endsection
@endsection
