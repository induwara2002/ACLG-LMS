<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Admin Dashboard')</title>

        <link rel="icon" type="image/png" href="{{ asset('images/government-logo.png') }}">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    </head>
    <body class="{{ request()->routeIs('admin.dashboard', 'admin.sabhas.dashboard') ? 'dashboard-theme' : '' }}">
            <x-page-loader />
            <div class="app-shell {{ auth()->user()?->isPradeshiyaSabhaAdmin() ? 'sabha-admin-shell' : 'd-flex' }}">
            @if (auth()->user()?->isPradeshiyaSabhaAdmin())
                @php($adminSabha = auth()->user()->pradeshiyaSabha)
                <x-admin.sabha-sidebar :sabha="$adminSabha" />
            @elseif (auth()->user()?->hasRole('LIBRARIAN'))
                <div class="admin-sidebar-backdrop" data-admin-sidebar-backdrop></div>
                <x-librarian-sidebar />
            @endif
            @if (! auth()->user()?->isPradeshiyaSabhaAdmin() && ! auth()->user()?->hasRole('LIBRARIAN'))
            <div class="admin-sidebar-backdrop" data-admin-sidebar-backdrop></div>
            <aside class="sidebar d-md-block" id="adminSidebar" data-sidebar>
                <div class="admin-sidebar-brand">
                    <img src="{{ asset('images/government-logo.png') }}" alt="" width="42" height="42">
                    <span><strong>{{ __('Pradeshiya Sabha') }}</strong><small>{{ __('Library Management System') }}</small></span>
                </div>
                <nav class="nav flex-column p-3 gap-1" aria-label="{{ __('navigation.workspace') }}">
                    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">{{ __('navigation.dashboard') }}</a>
                    @if (auth()->user()?->isMainAdmin())
                        <a class="nav-link {{ request()->routeIs('admin.sabhas.*') ? 'active' : '' }}" href="{{ route('admin.sabhas.index') }}">{{ __('navigation.sabha_management') }}</a>
                        <a class="nav-link admin-nav-subitem {{ request()->routeIs('admin.sabhas.*') ? 'active' : '' }}" href="{{ route('admin.sabhas.index') }}#admin-assignments">{{ __('navigation.sabha_admins') }}</a>
                        <a class="nav-link admin-nav-subitem {{ request()->routeIs('admin.sabhas.*') ? 'active' : '' }}" href="{{ route('admin.sabhas.index') }}#library-records">{{ __('navigation.libraries') }}</a>
                        <a class="nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}" href="{{ route('admin.audit-logs.index') }}">{{ __('navigation.system_activity') }}</a>
                    @endif
                    <a class="nav-link {{ request()->routeIs('admin.books.*') ? 'active' : '' }}" href="{{ route('admin.books.index') }}">{{ __('navigation.books') }}</a>
                    <a class="nav-link {{ request()->routeIs('admin.members.*') ? 'active' : '' }}" href="{{ route('admin.members.index') }}">{{ __('navigation.users_members') }}</a>
                    <a class="nav-link {{ request()->routeIs('admin.book-copies.*') ? 'active' : '' }}" href="{{ route('admin.book-copies.index') }}">{{ __('navigation.book_copies') }}</a>
                    @if (auth()->user()?->isMainAdmin())
                        <a class="nav-link {{ request()->routeIs('admin.authors.*') ? 'active' : '' }}" href="{{ route('admin.authors.index') }}">{{ __('navigation.authors') }}</a>
                        <a class="nav-link {{ request()->routeIs('admin.publishers.*') ? 'active' : '' }}" href="{{ route('admin.publishers.index') }}">{{ __('navigation.publishers') }}</a>
                        <a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">{{ __('navigation.categories') }}</a>
                    @endif
                    <a class="nav-link {{ request()->routeIs('admin.borrowings.index') ? 'active' : '' }}" href="{{ route('admin.borrowings.index') }}">{{ __('navigation.borrowing_books') }}</a>
                    <a class="nav-link {{ request()->routeIs('admin.returns.index', 'librarian.returns.index') ? 'active' : '' }}" href="{{ route(auth()->user()->hasRole('LIBRARIAN') ? 'librarian.returns.index' : 'admin.returns.index') }}">{{ __('navigation.returns') }}</a>
                    @if (auth()->user()?->isPradeshiyaSabhaAdmin())
                        <a class="nav-link {{ request()->routeIs('admin.issue-books') ? 'active' : '' }}" href="{{ route('admin.issue-books') }}">{{ __('navigation.issue_book') }}</a>
                        <a class="nav-link {{ request()->routeIs('admin.renewals.index', 'librarian.renewals.index') ? 'active' : '' }}" href="{{ route(auth()->user()->hasRole('LIBRARIAN') ? 'librarian.renewals.index' : 'admin.renewals.index') }}">{{ __('navigation.renewals') }}</a>
                        <a class="nav-link {{ request()->routeIs('admin.borrowings.index') && request('status') === 'OVERDUE' ? 'active' : '' }}" href="{{ route('admin.borrowings.index', ['status' => 'OVERDUE']) }}">{{ __('navigation.overdue_books') }}</a>
                        <a class="nav-link {{ request()->routeIs('admin.authors.index') ? 'active' : '' }}" href="{{ route('admin.authors.index') }}">{{ __('navigation.authors') }}</a>
                        <a class="nav-link {{ request()->routeIs('admin.publishers.index') ? 'active' : '' }}" href="{{ route('admin.publishers.index') }}">{{ __('navigation.publishers') }}</a>
                        <a class="nav-link {{ request()->routeIs('admin.categories.index') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">{{ __('navigation.categories') }} / DDC</a>
                    @endif
                    <a class="nav-link {{ request()->routeIs('admin.fines.index') ? 'active' : '' }}" href="{{ route('admin.fines.index') }}">{{ __('navigation.fines') }}</a>
                    <a class="nav-link {{ request()->routeIs('admin.payments.index') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}">{{ __('navigation.payment') }}</a>
                    <a class="nav-link {{ request()->routeIs('admin.lost-damaged.index') ? 'active' : '' }}" href="{{ route('admin.lost-damaged.index') }}">{{ __('navigation.lost_damaged') }}</a>
                    <a class="nav-link {{ request()->routeIs('admin.reports.index') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}">{{ __('navigation.reports') }}</a>
                    @if (auth()->user()?->hasRole('SABHA_ADMIN'))
                        <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}">{{ __('navigation.library_settings') }}</a>
                    @endif
                    <a class="nav-link {{ request()->routeIs('account.profile') ? 'active' : '' }}" href="{{ route('account.profile') }}">{{ __('navigation.profile') }}</a>
                    <a class="nav-link {{ request()->routeIs('account.password.*') ? 'active' : '' }}" href="{{ route('account.password.edit') }}">{{ __('navigation.change_password') }}</a>
                </nav>
            </aside>
            @endif

            <div class="main-content w-100 {{ auth()->user()?->isPradeshiyaSabhaAdmin() ? 'ps-admin-main' : '' }}">
                @if (auth()->user()?->isPradeshiyaSabhaAdmin())
                    <x-admin.sabha-header :sabha="$adminSabha" :overdue-count="$alerts['overdue'] ?? 0" />
                @elseif (auth()->user()?->hasRole('LIBRARIAN'))
                    <nav class="navbar navbar-custom mb-4 librarian-admin-topbar">
                        <div class="container-fluid">
                            <button class="btn btn-outline-secondary d-lg-none" type="button" data-sidebar-toggle data-open-label="{{ __('common.open_navigation') }}" data-close-label="{{ __('common.close_navigation') }}" aria-controls="librarianAdminSidebar" aria-expanded="false" aria-label="{{ __('common.open_navigation') }}">{{ __('common.menu') }}</button>
                            <strong>{{ __('Pradeshiya Sabha') }} · {{ __('Library Management System') }}</strong>
                            <div class="ms-auto d-flex align-items-center gap-3">
                                <x-language-switcher />
                                <a class="admin-alert-link" href="{{ route('librarian.borrowings.index', ['status' => 'OVERDUE']) }}">{{ __('Overdue') }} {{ $alerts['overdue'] ?? 0 }}</a>
                                <a class="text-muted text-decoration-none" href="{{ route('account.profile') }}">{{ auth()->user()->name }}</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('navigation.logout') }}</button>
                                </form>
                            </div>
                        </div>
                    </nav>
                @else
                <nav class="navbar navbar-expand-lg navbar-custom mb-4">
                    <div class="container-fluid">
                        <button class="btn btn-outline-secondary d-lg-none" type="button" data-sidebar-toggle data-open-label="{{ __('common.open_navigation') }}" data-close-label="{{ __('common.close_navigation') }}" aria-controls="adminSidebar" aria-expanded="false" aria-label="{{ __('common.open_navigation') }}">
                            {{ __('common.menu') }}
                        </button>
                        <div class="ms-auto d-flex align-items-center gap-3">
                            <x-language-switcher />
                            <a class="admin-alert-link" href="{{ route('admin.borrowings.index', ['status' => 'OVERDUE']) }}" aria-label="{{ __('View') }} {{ $alerts['overdue'] ?? 0 }} overdue book alerts">
                                <span aria-hidden="true">●</span> {{ __('Overdue') }} {{ $alerts['overdue'] ?? 0 }}
                            </a>
                            <a class="text-muted text-decoration-none" href="{{ route('account.profile') }}">{{ auth()->user()->name }} · {{ __('Super Admin') }}</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Logout') }}</button>
                            </form>
                        </div>
                    </div>
                </nav>
                @endif

                <div class="{{ auth()->user()?->isPradeshiyaSabhaAdmin() ? 'ps-admin-content' : '' }}">
                @if (session('success'))
                    <div class="alert alert-success">{{ __(session('success')) }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">{{ __(session('error')) }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ __($error) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <nav class="inner-page-breadcrumbs" aria-label="{{ __('Breadcrumb') }}">
                    <a href="{{ auth()->user()?->isPradeshiyaSabhaAdmin() || auth()->user()?->isMainAdmin() ? route('admin.dashboard') : (auth()->user()?->hasRole('LIBRARIAN') ? route('librarian.dashboard') : route('home')) }}">{{ __('Dashboard') }}</a>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page">@yield('title')</span>
                </nav>

                @yield('content')

                <footer class="system-footer">
                    <img src="{{ asset('images/sri-lanka-state-emblem.png') }}" alt="Sri Lankan State Emblem" width="56" height="56">
                    <div class="system-footer-copy">
                        <strong lang="si">{{ config('library.footer_office_name', 'ප්‍රාදේශීය ලේකම් කාර්යාලය') }}</strong>
                        <span lang="si">පුස්තකාල කළමනාකරණ පද්ධතිය</span>
                        <small>© {{ date('Y') }} {{ config('library.footer_office_name', 'ප්‍රාදේශීය ලේකම් කාර්යාලය') }} · පුස්තකාල කළමනාකරණ පද්ධතිය</small>
                    </div>
                </footer>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="{{ asset('js/app.js') }}"></script>
        @yield('scripts')
    </body>
</html>
