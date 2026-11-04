@extends('layouts.app')

@section('title', __('Login'))

@section('content')
<main class="login-page">
    <div class="login-shell">
        <section class="login-identity">
            <a class="login-home-link" href="{{ route('home') }}">{{ __('← Return to the public library') }}</a>
            <div class="login-institution">
                <img src="{{ asset('images/government-logo.png') }}" alt="Sri Lankan government emblem" width="72" height="72">
                <span><strong>{{ __('Pradeshiya Sabha – Galle') }}</strong><small>{{ __('Library Management System') }}</small></span>
            </div>
            <div class="login-welcome">
                <p class="section-kicker">{{ __('Public library services') }}</p>
                <h1>{{ __('Knowledge belongs to everyone.') }}</h1>
                <p>{{ __('Sign in to access your library workspace, manage your collection or check your membership.') }}</p>
            </div>
            <div class="login-visual" aria-hidden="true">
                <span class="login-book login-book-one"></span>
                <span class="login-book login-book-two"></span>
                <span class="login-book login-book-three"></span>
                <span class="login-visual-caption">{{ __('A community of readers, learners and discovery.') }}</span>
            </div>
        </section>
        <section class="login-form-panel" aria-labelledby="login-title">
            <div class="login-form-card">
                <p class="section-kicker">{{ __('Secure account access') }}</p>
                <h2 id="login-title">{{ __('Welcome back') }}</h2>
                <p class="login-form-intro">{{ __('Enter your account details to continue.') }}</p>
                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">{{ __('Email address') }}</label>
                        <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" autocomplete="username" placeholder="name@example.com" required autofocus aria-describedby="email-error">
                        @error('email')<span id="email-error" class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">{{ __('Password') }}</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" autocomplete="current-password" placeholder="{{ __('Enter your password') }}" required aria-describedby="password-error">
                        @error('password')<span id="password-error" class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1" @checked(old('remember'))>
                        <label class="form-check-label" for="remember">{{ __('Remember me') }}</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 login-submit">{{ __('Sign in securely') }}</button>
                </form>
                <p class="login-support">{{ __('Need help accessing your account? Please contact the library office.') }}</p>
                <p class="login-support mb-0">{{ __('Need a library account?') }} <a href="{{ route('register') }}">{{ __('member_registration.title') }}</a></p>
            </div>
        </section>
    </div>
</main>
@endsection
