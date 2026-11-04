@extends('layouts.admin')

@section('title', __('Library Settings'))

@section('content')
@php($settingLabels = [
    'name_si' => 'reports.sinhala_sabha_name',
    'library_name' => 'Library name',
    'library_address' => 'Address',
    'contact_number' => 'Contact number',
    'library_email' => 'Email',
    'opening_hours' => 'Opening hours',
    'registration_fee' => 'Registration fee',
    'monthly_membership_fee' => 'Monthly membership fee',
    'late_renewal_fee' => 'Late renewal fee',
    'late_renewal_penalty' => 'Late renewal penalty',
    'membership_duration_months' => 'Membership duration (months)',
    'individual_borrowing_limit' => 'Individual borrowing limit',
    'family_borrowing_limit' => 'Family borrowing limit',
    'issue_period_days' => 'Issue period (days)',
    'maximum_renewals' => 'Maximum renewals',
    'overdue_fine_amount' => 'Overdue fine amount',
    'currency' => 'Currency code',
    'timezone' => 'Timezone',
])
@php($settingSections = [
    'Sabha identity' => ['name_si'],
    'Library information' => ['library_name', 'library_address', 'contact_number', 'library_email', 'opening_hours'],
    'Fees and membership' => ['registration_fee', 'monthly_membership_fee', 'late_renewal_fee', 'late_renewal_penalty', 'membership_duration_months'],
    'Borrowing rules' => ['individual_borrowing_limit', 'family_borrowing_limit', 'issue_period_days', 'maximum_renewals', 'overdue_fine_amount'],
    'Regional preferences' => ['currency', 'timezone'],
])
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="page-title">{{ __('Library Settings') }}</h1><p class="page-subtitle mb-0">{{ $sabha->name }}</p></div>
</div>
@unless ($settingsStatus['complete'])
    <div class="alert alert-warning">
        <strong>{{ __('Configuration is incomplete.') }}</strong>
        @if ($settingsStatus['missing'] !== [])
            <div>{{ __('Missing:') }} {{ collect($settingsStatus['missing'])->map(fn ($key) => __($settingLabels[$key] ?? $key))->implode(', ') }}</div>
        @endif
        @if ($settingsStatus['invalid'] !== [])
            <div>{{ __('Invalid:') }} {{ collect($settingsStatus['invalid'])->map(fn ($key) => __($settingLabels[$key] ?? $key))->implode(', ') }}</div>
        @endif
    </div>
@endunless
<form method="POST" action="{{ route('admin.settings.update') }}" class="settings-form">
    @csrf
    @method('PUT')
    @foreach ($settingSections as $section => $keys)
        <section class="card settings-section">
            <div class="card-body">
                <h2>{{ __($section) }}</h2>
                <div class="row g-3">
                    @foreach ($keys as $key)
                        @php($label = $settingLabels[$key])
                        <div class="col-sm-6 col-lg-4">
                            <label class="form-label" for="setting_{{ $key }}">{{ __($label) }} @if (in_array($key, \App\Services\PradeshiyaSabhaSettingsService::REQUIRED_SETTINGS, true))<span class="required-mark">*</span>@endif</label>
                            <input class="form-control" id="setting_{{ $key }}" name="{{ $key }}" value="{{ old($key, $sabha->setting($key)) }}" @if (in_array($key, \App\Services\PradeshiyaSabhaSettingsService::REQUIRED_SETTINGS, true)) required @endif @if ($key === 'library_email') type="email" @endif>
                            @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach
    <div class="d-flex gap-2 mt-3"><button class="btn btn-primary" type="submit">{{ __('Save Library Settings') }}</button><a class="btn btn-outline-secondary" href="{{ url()->previous() }}">{{ __('Cancel') }}</a></div>
</form>
@endsection
