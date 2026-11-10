<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Member Portal')</title>
        <link rel="icon" type="image/png" href="{{ asset('images/government-logo.png') }}">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    </head>
    <body class="{{ request()->routeIs('member.dashboard') ? 'dashboard-theme' : '' }}">
        <x-page-loader />
        @php
            $portalMember = auth()->user()?->member;
            $portalSabha = $portalMember?->pradeshiyaSabha;
            $portalLibraryName = $portalSabha?->setting('library_name') ?: ($portalSabha?->name ?? __('dashboard.member_portal'));
        @endphp
        <div class="member-portal">
            <header class="member-portal-header">
                <a class="member-portal-brand" href="{{ route('member.dashboard') }}">
                    <img src="{{ $portalSabha?->logo_path ? route('sabhas.logo', $portalSabha) : asset('images/government-logo.png') }}" alt="{{ $portalSabha?->name ?? __('dashboard.library') }} logo" width="48" height="48">
                    <span><strong>{{ $portalLibraryName }}</strong><small>{{ $portalSabha?->name ?? __('dashboard.member_portal') }}</small></span>
                </a>
                <div class="member-portal-user">
                    <span>{{ auth()->user()?->name }}</span>
                    <x-language-switcher />
                </div>
            </header>
            <div class="member-portal-shell">
                <aside class="member-portal-sidebar" aria-label="{{ __('navigation.member_navigation') }}">
                    <p class="member-sidebar-label">{{ __('navigation.member_navigation') }}</p>
                    <nav class="member-portal-nav">
                        <a class="{{ request()->routeIs('member.dashboard') && ! request()->query('section') ? 'active' : '' }}" href="{{ route('member.dashboard') }}"><span aria-hidden="true">⌂</span>{{ __('navigation.dashboard') }}</a>
                        <a class="{{ request()->routeIs('member.books*') ? 'active' : '' }}" href="{{ route('member.books.search') }}"><span aria-hidden="true">⌕</span>{{ __('navigation.browse_books') }}</a>
                        <a class="{{ request()->routeIs('member.borrowings') ? 'active' : '' }}" href="{{ route('member.borrowings') }}"><span aria-hidden="true">▤</span>{{ __('navigation.my_borrowings') }}</a>
                        <a href="{{ route('member.dashboard') }}#reservations"><span aria-hidden="true">◷</span>{{ __('navigation.my_reservations') }}</a>
                        <a href="{{ route('member.dashboard') }}#reading-history"><span aria-hidden="true">▧</span>{{ __('navigation.reading_history') }}</a>
                        <a class="{{ request()->routeIs('member.membership') ? 'active' : '' }}" href="{{ route('member.membership') }}"><span aria-hidden="true">▣</span>{{ __('navigation.membership_details') }}</a>
                        <a class="{{ request()->routeIs('member.alerts') ? 'active' : '' }}" href="{{ route('member.alerts') }}"><span aria-hidden="true">♧</span>{{ __('navigation.notifications') }}</a>
                        <a class="{{ request()->routeIs('member.profile') ? 'active' : '' }}" href="{{ route('member.profile') }}"><span aria-hidden="true">◉</span>{{ __('navigation.profile') }}</a>
                    </nav>
                    <p class="member-sidebar-label member-sidebar-secondary-label">{{ __('navigation.account_services') }}</p>
                    <nav class="member-portal-nav member-portal-secondary-nav">
                        <a class="{{ request()->routeIs('member.fines') ? 'active' : '' }}" href="{{ route('member.fines') }}">{{ __('navigation.my_fines') }}</a>
                        <a class="{{ request()->routeIs('member.payments') ? 'active' : '' }}" href="{{ route('member.payments') }}">{{ __('navigation.my_payments') }}</a>
                        <a class="{{ request()->routeIs('member.password.*') ? 'active' : '' }}" href="{{ route('member.password.edit') }}">{{ __('navigation.change_password') }}</a>
                    </nav>
                    <form class="member-sidebar-logout" method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"><span aria-hidden="true">↪</span>{{ __('navigation.logout') }}</button>
                    </form>
                </aside>
                <div class="member-portal-main">
                    <main class="member-portal-content">
                        @if (session('success'))<div class="alert alert-success">{{ __(session('success')) }}</div>@endif
                        @if (session('error'))<div class="alert alert-danger">{{ __(session('error')) }}</div>@endif
                        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ __($error) }}</li>@endforeach</ul></div>@endif
                        <nav class="inner-page-breadcrumbs" aria-label="{{ __('common.breadcrumb') }}"><a href="{{ route('member.dashboard') }}">{{ __('navigation.dashboard') }}</a><span aria-hidden="true">/</span><span aria-current="page">@yield('title')</span></nav>
                        @yield('content')
                    </main>
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
