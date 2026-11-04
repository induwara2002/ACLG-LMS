@props(['sabha'])

<div class="ps-sidebar-backdrop" data-ps-sidebar-backdrop></div>
<aside class="ps-admin-sidebar" id="psAdminSidebar" data-ps-sidebar aria-label="{{ __('navigation.library_settings') }}">
    <a class="ps-sidebar-brand" href="{{ route('admin.dashboard') }}">
        <img src="{{ $sabha?->logo_path ? route('sabhas.logo', $sabha) : asset('images/government-logo.png') }}" alt="" width="48" height="48">
        <span>
            <strong>{{ $sabha?->setting('library_name') ?: ($sabha?->name ?? 'Library') }}</strong>
            <small>{{ __('navigation.library_settings') }}</small>
        </span>
    </a>

    <p class="ps-sidebar-section-label">{{ __('navigation.workspace') }}</p>
    <nav class="ps-sidebar-nav">
        <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
            <span class="ps-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="8" height="8" rx="1.5"></rect><rect x="13" y="3" width="8" height="5" rx="1.5"></rect><rect x="13" y="10" width="8" height="11" rx="1.5"></rect><rect x="3" y="13" width="8" height="8" rx="1.5"></rect></svg></span>
            <span>{{ __('navigation.dashboard') }}</span>
        </a>
        <a class="{{ request()->routeIs('admin.books.*') ? 'active' : '' }}" href="{{ route('admin.books.index') }}">
            <span class="ps-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21V5.5Z"></path><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20M8 7h8M8 10h8"></path></svg></span>
            <span>{{ __('navigation.books') }}</span>
        </a>
        <a class="{{ request()->routeIs('admin.members.*') ? 'active' : '' }}" href="{{ route('admin.members.index') }}">
            <span class="ps-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20a6.5 6.5 0 0 1 13 0M18 8h5m-2.5-2.5v5"></path></svg></span>
            <span>{{ __('navigation.members') }}</span>
        </a>
        <a class="{{ request()->routeIs('admin.member-applications.*') ? 'active' : '' }}" href="{{ route('admin.member-applications.index') }}">
            <span class="ps-nav-icon" aria-hidden="true">✓</span>
            <span>{{ __('navigation.member_applications') }}</span>
        </a>
        <a class="{{ request()->routeIs('admin.issue-books', 'admin.borrowings.create') ? 'active' : '' }}" href="{{ route('admin.issue-books') }}">
            <span class="ps-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h14m-5-5 5 5-5 5"></path><path d="M4 5h6M4 19h6"></path></svg></span>
            <span>{{ __('navigation.issue_book') }}</span>
        </a>
        <a class="{{ request()->routeIs('admin.borrowings.*') ? 'active' : '' }}" href="{{ route('admin.borrowings.index') }}">
            <span class="ps-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h11"></path><circle cx="19" cy="18" r="2"></circle></svg></span>
            <span>{{ __('navigation.borrowing_books') }}</span>
        </a>
        <a class="{{ request()->routeIs('admin.returns.*') ? 'active' : '' }}" href="{{ route('admin.returns.index') }}">
            <span class="ps-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M19 12H5m5 5-5-5 5-5"></path><path d="M20 5h-6M20 19h-6"></path></svg></span>
            <span>{{ __('navigation.return_book') }}</span>
        </a>
        <a class="{{ request()->routeIs('admin.borrowings.index') && request('status') === 'OVERDUE' ? 'active' : '' }}" href="{{ route('admin.borrowings.index', ['status' => 'OVERDUE']) }}">
            <span class="ps-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg></span>
            <span>{{ __('navigation.overdue_books') }}</span>
        </a>
        <a class="{{ request()->routeIs('admin.renewals.*') ? 'active' : '' }}" href="{{ route('admin.renewals.index') }}"><span class="ps-nav-icon" aria-hidden="true">↻</span><span>{{ __('navigation.renewals') }}</span></a>
        <a class="{{ request()->routeIs('admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}"><span class="ps-nav-icon" aria-hidden="true">¤</span><span>{{ __('navigation.payment') }}</span></a>
        <a class="{{ request()->routeIs('admin.fines.*') ? 'active' : '' }}" href="{{ route('admin.fines.index') }}"><span class="ps-nav-icon" aria-hidden="true">$</span><span>{{ __('navigation.fines') }}</span></a>
        <a class="{{ request()->routeIs('admin.lost-damaged.*') ? 'active' : '' }}" href="{{ route('admin.lost-damaged.index') }}"><span class="ps-nav-icon" aria-hidden="true">!</span><span>{{ __('navigation.lost_damaged') }}</span></a>
        <a class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}">
            <span class="ps-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20V10m8 10V4m8 16v-7"></path><path d="M2 20h20"></path></svg></span>
            <span>{{ __('navigation.reports') }}</span>
        </a>
        @if (auth()->user()?->hasRole('SABHA_ADMIN'))
            <a class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}">
                <span class="ps-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="m19.4 15 .1.1 1.4 1.1-1.5 2.6-1.7-.6a8 8 0 0 1-1.5.9l-.3 1.8h-3l-.3-1.8a8 8 0 0 1-1.5-.9l-1.7.6-1.5-2.6L7.3 15a8 8 0 0 1 0-1.8l-1.4-1.1 1.5-2.6 1.7.6a8 8 0 0 1 1.5-.9l.3-1.8h3l.3 1.8a8 8 0 0 1 1.5.9l1.7-.6 1.5 2.6-1.4 1.1a8 8 0 0 1-.1 1.8Z"></path></svg></span>
                <span>{{ __('navigation.library_settings') }}</span>
            </a>
        @endif
    </nav>

    <div class="ps-sidebar-bottom">
        <a class="{{ request()->routeIs('account.profile') ? 'active' : '' }}" href="{{ route('account.profile') }}">
            <span class="ps-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg></span>
            <span>{{ __('navigation.profile') }}</span>
        </a>
        <a class="{{ request()->routeIs('account.password.*') ? 'active' : '' }}" href="{{ route('account.password.edit') }}">
            <span class="ps-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 1 1 8 0v3m-4 4v3"></path></svg></span>
            <span>{{ __('Account password') }}</span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">
                <span class="ps-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5m5 5H3"></path><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"></path></svg></span>
                <span>{{ __('navigation.logout') }}</span>
            </button>
        </form>
    </div>
</aside>
