<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Library Management System')</title>

        <link rel="icon" type="image/png" href="{{ asset('images/government-logo.png') }}">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    </head>
    <body>
        <x-page-loader />
        @unless (request()->routeIs('home', 'login'))
            <nav class="navbar navbar-expand-lg navbar-custom">
                <div class="container">
                    <a class="navbar-brand fw-bold app-institution-brand" href="{{ auth()->check() ? auth()->user()->dashboardRoute() : route('home') }}">
                        <img src="{{ asset('images/government-logo.png') }}" alt="" width="34" height="34">
                        <span>{{ __('Pradeshiya Sabha – Galle') }}<small>{{ __('Library Management System') }}</small></span>
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="mainNavbar">
                        <ul class="navbar-nav ms-auto align-items-lg-center">
                            <li class="nav-item me-lg-2"><x-language-switcher /></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">{{ __('Home') }}</a></li>
                            @auth
                                <li class="nav-item"><a class="nav-link" href="{{ route('account.profile') }}">{{ __('Profile') }}</a></li>
                                <li class="nav-item">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary ms-lg-3">{{ __('Logout') }}</button>
                                    </form>
                                </li>
                            @else
                                <li class="nav-item"><a class="btn btn-primary ms-lg-3" href="{{ route('login') }}">{{ __('Login') }}</a></li>
                            @endauth
                        </ul>
                    </div>
                </div>
            </nav>
        @endunless

        @unless (request()->routeIs('home'))
            <main>
        @endunless
            @if (session('success'))
                <div class="container mt-3">
                    <div class="alert alert-success">{{ __(session('success')) }}</div>
                </div>
            @endif

            @if (session('error'))
                <div class="container mt-3">
                    <div class="alert alert-danger">{{ __(session('error')) }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="container mt-3">
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ __($error) }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @unless (request()->routeIs('home', 'login'))
                <div class="container inner-page-breadcrumbs app-page-breadcrumbs">
                    <a href="{{ auth()->check() ? auth()->user()->dashboardRoute() : route('home') }}">{{ __('Dashboard') }}</a>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page">@yield('title')</span>
                </div>
            @endunless

            @yield('content')
        @unless (request()->routeIs('home'))
            </main>
        @endunless

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="{{ asset('js/app.js') }}"></script>
        @yield('scripts')
    </body>
</html>
