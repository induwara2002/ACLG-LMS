@extends('layouts.app')

@section('title', __('member_registration.title'))

@section('content')
<main class="registration-page">
    <section class="registration-card">
        <p class="section-kicker">{{ __('member_registration.kicker') }}</p>
        <h1 class="page-title">{{ __('member_registration.title') }}</h1>
        <p class="page-subtitle">{{ __('member_registration.intro') }}</p>

        <form method="POST" action="{{ route('register.store') }}" class="row g-3">
            @csrf
            <div class="col-md-6">
                <label for="full_name" class="form-label">{{ __('member_registration.full_name') }}</label>
                <input id="full_name" name="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name') }}" autocomplete="name" required>
                @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label">{{ __('member_registration.email') }}</label>
                <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" autocomplete="email" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="nic" class="form-label">{{ __('member_registration.nic') }}</label>
                <input id="nic" name="nic" class="form-control @error('nic') is-invalid @enderror" value="{{ old('nic') }}" required>
                @error('nic')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="phone" class="form-label">{{ __('member_registration.phone') }}</label>
                <input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" autocomplete="tel" required>
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="address" class="form-label">{{ __('member_registration.address') }}</label>
                <textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror" rows="3" autocomplete="street-address" required>{{ old('address') }}</textarea>
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="pradeshiya_sabha_id" class="form-label">{{ __('member_registration.sabha') }}</label>
                <select id="pradeshiya_sabha_id" name="pradeshiya_sabha_id" class="form-select @error('pradeshiya_sabha_id') is-invalid @enderror" required>
                    <option value="">{{ __('member_registration.choose_sabha') }}</option>
                    @foreach ($sabhas as $sabha)
                        <option value="{{ $sabha->id }}" @selected((string) old('pradeshiya_sabha_id') === (string) $sabha->id)>{{ $sabha->name }}</option>
                    @endforeach
                </select>
                @error('pradeshiya_sabha_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="membership_type_id" class="form-label">{{ __('member_registration.membership_type') }}</label>
                <select id="membership_type_id" name="membership_type_id" class="form-select @error('membership_type_id') is-invalid @enderror" required>
                    <option value="">{{ __('member_registration.choose_membership_type') }}</option>
                    @foreach ($membershipTypes as $membershipType)
                        <option value="{{ $membershipType->id }}" @selected((string) old('membership_type_id') === (string) $membershipType->id)>{{ $membershipType->name }}</option>
                    @endforeach
                </select>
                @error('membership_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="password" class="form-label">{{ __('member_registration.password') }}</label>
                <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="password_confirmation" class="form-label">{{ __('member_registration.confirm_password') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
            </div>
            <div class="col-12">
                <div class="registration-notice">{{ __('member_registration.pending_notice') }}</div>
            </div>
            <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <a href="{{ route('login') }}">{{ __('member_registration.already_registered') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('member_registration.submit') }}</button>
            </div>
        </form>
    </section>
</main>
@endsection
