@extends(auth()->user()?->hasRole('MEMBER') ? 'layouts.member' : 'layouts.admin')

@section('title', __('My Profile'))

@section('content')
@php
    $profileRole = auth()->user()->isMainAdmin()
        ? __('Super Admin')
        : (auth()->user()->isPradeshiyaSabhaAdmin()
            ? __('Sabha Admin')
            : __(\Illuminate\Support\Str::title(strtolower(auth()->user()->role))));
@endphp
<div class="container py-5 account-profile-page">
    <header class="profile-page-heading">
        <span class="profile-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>
        <div><p class="section-kicker">{{ __('Account') }}</p><h1 class="page-title">{{ __('My Profile') }}</h1><p class="page-subtitle">{{ __('Your account and security information.') }}</p></div>
        <span class="badge {{ auth()->user()->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ __(auth()->user()->is_active ? 'Active account' : 'Inactive account') }}</span>
    </header>
    <div class="profile-panels">
        <section class="card account-profile-card">
            <div class="profile-panel-heading"><p class="section-kicker">{{ __('Identity') }}</p><h2>{{ __('Profile information') }}</h2></div>
            <dl class="row mb-0">
                <dt class="col-sm-4">{{ __('Name') }}</dt><dd class="col-sm-8">{{ auth()->user()->name }}</dd>
                <dt class="col-sm-4">{{ __('Email') }}</dt><dd class="col-sm-8">{{ auth()->user()->email }}</dd>
                <dt class="col-sm-4">{{ __('Role') }}</dt><dd class="col-sm-8">{{ $profileRole }}</dd>
                @if (auth()->user()->pradeshiyaSabha)
                    <dt class="col-sm-4">{{ __('Pradeshiya Sabha') }}</dt><dd class="col-sm-8">{{ auth()->user()->pradeshiyaSabha->name }}</dd>
                @endif
            </dl>
        </section>
        <section class="card account-profile-card profile-security-card">
            <div class="profile-panel-heading"><p class="section-kicker">{{ __('Account protection') }}</p><h2>{{ __('Password &amp; security') }}</h2></div>
            <p>{{ __('Keep your account secure by using a strong, private password.') }}</p>
            @if (auth()->user()->hasRole('MEMBER'))
                <a class="btn btn-outline-primary" href="{{ route('member.password.edit') }}">{{ __('Change password') }}</a>
            @elseif (auth()->user()->isMainAdmin() || auth()->user()->isPradeshiyaSabhaAdmin())
                <a class="btn btn-outline-primary" href="{{ route('account.password.edit') }}">{{ __('Change password') }}</a>
            @else
                <span class="text-muted small">{{ __('Contact your system administrator for account-security assistance.') }}</span>
            @endif
        </section>
    </div>
</div>
@endsection
