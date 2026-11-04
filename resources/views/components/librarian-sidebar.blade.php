<aside class="sidebar d-lg-block librarian-admin-sidebar" id="librarianAdminSidebar" data-sidebar>
    <a class="admin-sidebar-brand" href="{{ route('librarian.dashboard') }}">
        <img src="{{ asset('images/government-logo.png') }}" alt="" width="42" height="42">
        <span><strong>{{ __('Pradeshiya Sabha') }}</strong><small>{{ __('Library Management System') }}</small></span>
    </a>
    <nav class="nav flex-column p-3 gap-1" aria-label="{{ __('navigation.library_operations') }}">
        <a class="nav-link {{ request()->routeIs('librarian.dashboard') ? 'active' : '' }}" href="{{ route('librarian.dashboard') }}">{{ __('navigation.dashboard') }}</a>
        <a class="nav-link {{ request()->routeIs('librarian.books.*') ? 'active' : '' }}" href="{{ route('librarian.books.search') }}">{{ __('navigation.books') }}</a>
        <a class="nav-link {{ request()->routeIs('librarian.members.*') ? 'active' : '' }}" href="{{ route('librarian.members.index') }}">{{ __('navigation.members') }}</a>
        <a class="nav-link {{ request()->routeIs('librarian.issue-books') ? 'active' : '' }}" href="{{ route('librarian.issue-books') }}">{{ __('navigation.issue_book') }}</a>
        <a class="nav-link {{ request()->routeIs('librarian.borrowings.*') ? 'active' : '' }}" href="{{ route('librarian.borrowings.index') }}">{{ __('navigation.borrowing_books') }}</a>
        <a class="nav-link {{ request()->routeIs('librarian.returns.*') ? 'active' : '' }}" href="{{ route('librarian.returns.index') }}">{{ __('navigation.returns') }}</a>
        <a class="nav-link {{ request()->routeIs('librarian.renewals.*') ? 'active' : '' }}" href="{{ route('librarian.renewals.index') }}">{{ __('navigation.renewals') }}</a>
        <a class="nav-link {{ request()->routeIs('librarian.payments.*') ? 'active' : '' }}" href="{{ route('librarian.payments.index') }}">{{ __('navigation.payment') }}</a>
        <a class="nav-link {{ request()->routeIs('librarian.fines.*') ? 'active' : '' }}" href="{{ route('librarian.fines.index') }}">{{ __('navigation.fines') }}</a>
        <a class="nav-link {{ request()->routeIs('librarian.lost-damaged.*') ? 'active' : '' }}" href="{{ route('librarian.lost-damaged.index') }}">{{ __('navigation.lost_damaged') }}</a>
        <a class="nav-link {{ request()->routeIs('librarian.reports.*') ? 'active' : '' }}" href="{{ route('librarian.reports.index') }}">{{ __('navigation.reports') }}</a>
        <a class="nav-link {{ request()->routeIs('account.profile') ? 'active' : '' }}" href="{{ route('account.profile') }}">{{ __('navigation.profile') }}</a>
        <form class="librarian-admin-logout" method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link" type="submit">{{ __('navigation.logout') }}</button></form>
    </nav>
</aside>
