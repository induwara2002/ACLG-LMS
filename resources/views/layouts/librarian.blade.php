<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Librarian Workspace')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/government-logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="librarian-app">
    <x-page-loader />
    <div class="librarian-sidebar-backdrop" data-librarian-backdrop></div>
    <aside class="librarian-sidebar" id="librarianSidebar" data-librarian-sidebar aria-label="{{ __('navigation.library_operations') }}">
        <a class="librarian-sidebar-brand" href="{{ route('librarian.dashboard') }}">
            <img src="{{ asset('images/government-logo.png') }}" alt="" width="46" height="46">
            <span><strong>{{ __('Pradeshiya Sabha – Galle') }}</strong><small>{{ __('Library workspace') }}</small></span>
        </a>
        <nav class="librarian-navigation">
            <p>{{ __('navigation.library_operations') }}</p>
            <a class="{{ request()->routeIs('librarian.dashboard') ? 'active' : '' }}" href="{{ route('librarian.dashboard') }}">{{ __('navigation.dashboard') }}</a>
            <a class="{{ request()->routeIs('librarian.books.*') ? 'active' : '' }}" href="{{ route('librarian.books.search') }}">{{ __('navigation.books') }}</a>
            <a class="{{ request()->routeIs('librarian.members.*') ? 'active' : '' }}" href="{{ route('librarian.members.index') }}">{{ __('navigation.members') }}</a>
            <a class="{{ request()->routeIs('librarian.issue-books', 'librarian.borrowings.create') ? 'active' : '' }}" href="{{ route('librarian.issue-books') }}">{{ __('navigation.issue_book') }}</a>
            <a class="{{ request()->routeIs('librarian.borrowings.*') ? 'active' : '' }}" href="{{ route('librarian.borrowings.index') }}">{{ __('navigation.borrowing_books') }}</a>
            <a class="{{ request()->routeIs('librarian.returns.*') ? 'active' : '' }}" href="{{ route('librarian.returns.index') }}">{{ __('navigation.returns') }}</a>
            <a class="{{ request()->routeIs('librarian.renewals.*') ? 'active' : '' }}" href="{{ route('librarian.renewals.index') }}">{{ __('navigation.renewals') }}</a>
            <a class="{{ request()->routeIs('librarian.payments.*') ? 'active' : '' }}" href="{{ route('librarian.payments.index') }}">{{ __('navigation.payment') }}</a>
            <a class="{{ request()->routeIs('librarian.fines.*') ? 'active' : '' }}" href="{{ route('librarian.fines.index') }}">{{ __('navigation.fines') }}</a>
            <a class="{{ request()->routeIs('librarian.lost-damaged.*') ? 'active' : '' }}" href="{{ route('librarian.lost-damaged.index') }}">{{ __('navigation.lost_damaged') }}</a>
            <a class="{{ request()->routeIs('librarian.reports.*') ? 'active' : '' }}" href="{{ route('librarian.reports.index') }}">{{ __('navigation.reports') }}</a>
        </nav>
        <div class="librarian-sidebar-bottom">
            <a class="{{ request()->routeIs('account.profile') ? 'active' : '' }}" href="{{ route('account.profile') }}">{{ __('navigation.profile') }}</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">{{ __('navigation.logout') }}</button></form>
        </div>
    </aside>
    <div class="librarian-main">
        <header class="librarian-topbar">
            <button class="librarian-menu-toggle" type="button" data-librarian-toggle data-open-label="{{ __('common.open_navigation') }}" data-close-label="{{ __('common.close_navigation') }}" aria-controls="librarianSidebar" aria-expanded="false" aria-label="{{ __('common.open_navigation') }}">☰</button>
            <div><strong>{{ __('Library Management System') }}</strong><small>{{ __('Librarian workspace') }}</small></div>
            <a class="librarian-alert-link" href="{{ route('librarian.borrowings.index', ['status' => 'OVERDUE']) }}">{{ __('Overdue') }} {{ $alerts['overdue'] ?? 0 }}</a>
            <x-language-switcher />
            <a href="{{ route('account.profile') }}" class="librarian-user">{{ auth()->user()->name }}</a>
        </header>
        <main class="librarian-content">
            @if (session('success'))<div class="alert alert-success">{{ __(session('success')) }}</div>@endif
            @if (session('error'))<div class="alert alert-danger">{{ __(session('error')) }}</div>@endif
            @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ __($error) }}</li>@endforeach</ul></div>@endif
            <nav class="inner-page-breadcrumbs" aria-label="{{ __('Breadcrumb') }}"><a href="{{ route('librarian.dashboard') }}">{{ __('Dashboard') }}</a><span aria-hidden="true">/</span><span aria-current="page">@yield('title')</span></nav>
            @yield('content')
            <footer class="system-footer">
                <img src="{{ asset('images/sri-lanka-state-emblem.png') }}" alt="Sri Lankan State Emblem" width="56" height="56">
                <div class="system-footer-copy">
                    <strong lang="si">{{ config('library.footer_office_name', 'ප්‍රාදේශීය ලේකම් කාර්යාලය') }}</strong>
                    <span lang="si">පුස්තකාල කළමනාකරණ පද්ධතිය</span>
                    <small>© {{ date('Y') }} {{ config('library.footer_office_name', 'ප්‍රාදේශීය ලේකම් කාර්යාලය') }} · පුස්තකාල කළමනාකරණ පද්ධතිය</small>
                </div>
            </footer>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @yield('scripts')
</body>
</html>
