@extends('layouts.admin')

@section('title', __('Add Member'))

@section('content')
@php($memberRoute = auth()->user()->hasRole('LIBRARIAN') ? 'librarian.members' : 'admin.members')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ __('Add') }} {{ __('Member') }}</h1>
        <p class="page-subtitle">{{ __('Register a new library member record') }}</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-info">
            {{ __('The member ID is generated automatically. Registration fees and membership duration are set by this Sabha.') }}
        </div>

        <form method="POST" action="{{ route($memberRoute . '.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="generated_member_id">{{ __('Member ID') }}</label>
                    <input id="generated_member_id" type="text" class="form-control" value="{{ __('Generated when saved') }}" readonly aria-describedby="member-id-help">
                    <div id="member-id-help" class="form-text">{{ __('The system assigns a unique member ID after registration.') }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Full Name') }}</label>
                    <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name') }}" required>
                    @error('full_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                    @error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="member-password">{{ __('Initial password') }}</label>
                    <input id="member-password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" minlength="12" required>
                    @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="member-password-confirmation">{{ __('Confirm initial password') }}</label>
                    <input id="member-password-confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" minlength="12" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('NIC') }}</label>
                    <input type="text" name="nic" class="form-control @error('nic') is-invalid @enderror" value="{{ old('nic') }}" required>
                    @error('nic') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Phone') }}</label>
                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" required>
                    @error('phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Membership Type') }}</label>
                    <select name="membership_type_id" class="form-select @error('membership_type_id') is-invalid @enderror" required>
                        <option value="">{{ __('Select membership type') }}</option>
                        @foreach ($membershipTypes as $type)
                            <option value="{{ $type->id }}" {{ old('membership_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('membership_type_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                @if (auth()->user()->isMainAdmin())
                    <div class="col-md-6">
                        <label class="form-label" for="sabha_id">{{ __('Pradeshiya Sabha') }}</label>
                        <select id="sabha_id" name="sabha_id" class="form-select @error('sabha_id') is-invalid @enderror" @required($activeSabhaId === null)>
                            <option value="">{{ __('Select a Pradeshiya Sabha') }}</option>
                            @foreach ($sabhas as $sabha)
                                <option value="{{ $sabha->id }}" @selected((string) old('sabha_id', $activeSabhaId) === (string) $sabha->id)>{{ $sabha->name }}</option>
                            @endforeach
                        </select>
                        @error('sabha_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                @endif
                <div class="col-12">
                    <label class="form-label">{{ __('Address') }}</label>
                    <textarea name="address" rows="3" class="form-control @error('address') is-invalid @enderror">{{ old('address') }}</textarea>
                    @error('address') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-12"><div class="alert alert-secondary mb-0">{{ __('Registration date, membership dates, status, and the configured registration payment are created automatically.') }}</div></div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('Save') }} {{ __('Member') }}</button>
                <a href="{{ route($memberRoute . '.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
