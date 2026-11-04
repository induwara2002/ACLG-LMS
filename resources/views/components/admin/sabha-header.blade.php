@props(['sabha', 'overdueCount' => 0])

<header class="ps-admin-header">
    <button class="ps-mobile-menu-toggle" type="button" data-ps-sidebar-toggle data-open-label="{{ __('common.open_navigation') }}" data-close-label="{{ __('common.close_navigation') }}" aria-controls="psAdminSidebar" aria-expanded="false" aria-label="{{ __('common.open_navigation') }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
    </button>
    <a class="ps-header-identity" href="{{ route('admin.dashboard') }}">
        <img src="{{ $sabha?->logo_path ? route('sabhas.logo', $sabha) : asset('images/government-logo.png') }}" alt="" width="42" height="42">
        <span><strong>{{ $sabha?->name ?? 'Pradeshiya Sabha' }}</strong><small>{{ __('Library Management System') }}</small></span>
    </a>
    <div class="ps-header-tools">
        <x-language-switcher />
        <a class="ps-notification-link" href="{{ route('admin.dashboard') }}#dashboard-alerts" aria-label="{{ __('View dashboard notifications') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"></path></svg>
            @if ($overdueCount > 0)<span>{{ $overdueCount }}</span>@endif
        </a>
        <span class="ps-header-date">{{ now()->format('D, d M Y · g:i A') }}</span>
        <div class="dropdown">
            <button class="ps-admin-user dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="ps-admin-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>
                <span class="ps-admin-user-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ __('dashboard.administrator') }}</small></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end ps-profile-menu">
                <a class="dropdown-item" href="{{ route('account.profile') }}">{{ __('Profile') }}</a>
                <a class="dropdown-item" href="{{ route('account.password.edit') }}">{{ __('Account password') }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="dropdown-item" type="submit">{{ __('Logout') }}</button>
                </form>
            </div>
        </div>
    </div>
</header>
