@extends('layouts.app')

@section('title', __('member_registration.submitted_title'))

@section('content')
<main class="registration-page">
    <section class="registration-card registration-confirmation">
        <span class="registration-confirmation-icon" aria-hidden="true">✓</span>
        <p class="section-kicker">{{ __('member_registration.kicker') }}</p>
        <h1 class="page-title">{{ __('member_registration.submitted_title') }}</h1>
        <p class="page-subtitle">{{ __('member_registration.submitted_message') }}</p>
        <div class="registration-notice">{{ __('member_registration.next_steps') }}</div>
        <a href="{{ route('login') }}" class="btn btn-primary mt-4">{{ __('member_registration.go_to_login') }}</a>
    </section>
</main>
@endsection
