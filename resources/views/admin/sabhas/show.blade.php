@extends('layouts.admin')

@section('title', $sabha->name)

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            @if ($sabha->logo_path)
                <img src="{{ route('sabhas.logo', $sabha) }}" alt="{{ __('Logo for :name', ['name' => $sabha->name]) }}" width="72" height="72" class="object-fit-contain">
            @else
                <div class="border rounded p-3 text-muted">{{ __('No logo') }}</div>
            @endif
            <div>
                <h1 class="page-title">{{ $sabha->name }}</h1>
                <div class="text-muted">{{ __('Code') }}: {{ $sabha->code }} · {{ __($sabha->is_active ? 'Active' : 'Inactive') }}</div>
            </div>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.sabhas.dashboard', $sabha) }}">{{ __('View Sabha Dashboard') }}</a>
    </div>

    <section class="mb-5">
        <h2 class="h5 mb-3">{{ __('Sabha details') }}</h2>
        <form method="POST" action="{{ route('admin.sabhas.update', $sabha) }}" class="row g-3">
            @csrf
            @method('PUT')
            <div class="col-md-4"><label class="form-label" for="name">{{ __('Name') }}</label><input class="form-control" id="name" name="name" value="{{ old('name', $sabha->name) }}" required maxlength="255"></div>
            <div class="col-md-2"><label class="form-label" for="code">{{ __('Code') }}</label><input class="form-control" id="code" name="code" value="{{ old('code', $sabha->code) }}" required maxlength="12"></div>
            <div class="col-md-6"><label class="form-label" for="address">{{ __('Address') }}</label><input class="form-control" id="address" name="address" value="{{ old('address', $sabha->address) }}" maxlength="5000"></div>
            <div class="col-md-3"><label class="form-label" for="phone">{{ __('Phone') }}</label><input class="form-control" id="phone" name="phone" value="{{ old('phone', $sabha->phone) }}" maxlength="50"></div>
            <div class="col-md-4"><label class="form-label" for="email">{{ __('Email') }}</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email', $sabha->email) }}" maxlength="255"></div>
            <div class="col-12"><button class="btn btn-outline-primary" type="submit">{{ __('Save Sabha details') }}</button></div>
        </form>
    </section>

    <section class="mb-5">
        <h2 class="h5 mb-3">{{ __('Organization status') }}</h2>
        <form method="POST" action="{{ route('admin.sabhas.status.update', $sabha) }}" class="d-flex align-items-center gap-3">
            @csrf
            @method('PATCH')
            <select class="form-select w-auto" name="is_active" aria-label="{{ __('Organization active status') }}">
                <option value="1" @selected($sabha->is_active)>{{ __('Active') }}</option>
                <option value="0" @selected(! $sabha->is_active)>{{ __('Inactive') }}</option>
            </select>
            <button class="btn btn-outline-primary" type="submit">{{ __('Save status') }}</button>
        </form>
    </section>

    <section class="mb-5">
        <h2 class="h5 mb-3">{{ __('Sabha logo') }}</h2>
        <form method="POST" action="{{ route('admin.sabhas.logo.upload', $sabha) }}" enctype="multipart/form-data" class="d-flex flex-wrap align-items-end gap-3">
            @csrf
            <div>
                <label class="form-label" for="logo">{{ __('Image file') }}</label>
                <input class="form-control" id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" required>
            </div>
            <button class="btn btn-outline-primary" type="submit">{{ __('Upload logo') }}</button>
        </form>
        @error('logo')<div class="text-danger mt-2">{{ $message }}</div>@enderror
        @if ($sabha->logo_path)
            <form method="POST" action="{{ route('admin.sabhas.logo.remove', $sabha) }}" class="mt-3">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger" type="submit">{{ __('Remove logo') }}</button>
            </form>
        @endif
    </section>

    <section class="mb-5">
        <h2 class="h5 mb-3">{{ __('Sabha administrator') }}</h2>
        @if ($sabha->adminAssignment?->user)
            <form method="POST" action="{{ route('admin.sabhas.admin.update', $sabha) }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-md-4">
                    <label class="form-label" for="admin_name">{{ __('Name') }}</label>
                    <input class="form-control" id="admin_name" name="name" value="{{ old('name', $sabha->adminAssignment->user->name) }}" required maxlength="255">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="admin_email">{{ __('Email') }}</label>
                    <input class="form-control" id="admin_email" type="email" name="email" value="{{ old('email', $sabha->adminAssignment->user->email) }}" required maxlength="255">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="admin_password">{{ __('New password (optional)') }}</label>
                    <input class="form-control" id="admin_password" type="password" name="password" autocomplete="new-password" minlength="12">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="admin_password_confirmation">{{ __('Confirm new password') }}</label>
                    <input class="form-control" id="admin_password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="admin_is_active">{{ __('Account status') }}</label>
                    <select class="form-select" id="admin_is_active" name="is_active">
                        <option value="1" @selected($sabha->adminAssignment->user->is_active)>{{ __('Active') }}</option>
                        <option value="0" @selected(! $sabha->adminAssignment->user->is_active)>{{ __('Inactive') }}</option>
                    </select>
                </div>
                <div class="col-12"><button class="btn btn-primary" type="submit">{{ __('Update administrator') }}</button></div>
            </form>
        @else
            <form method="POST" action="{{ route('admin.sabhas.admin.store', $sabha) }}" class="row g-3">
                @csrf
                <div class="col-md-4"><label class="form-label" for="admin_name">{{ __('Name') }}</label><input class="form-control" id="admin_name" name="name" value="{{ old('name') }}" required maxlength="255"></div>
                <div class="col-md-4"><label class="form-label" for="admin_email">{{ __('Email') }}</label><input class="form-control" id="admin_email" type="email" name="email" value="{{ old('email') }}" required maxlength="255"></div>
                <div class="col-md-4"><label class="form-label" for="admin_password">{{ __('Temporary password') }}</label><input class="form-control" id="admin_password" type="password" name="password" autocomplete="new-password" minlength="12" required></div>
                <div class="col-md-4"><label class="form-label" for="admin_password_confirmation">{{ __('Confirm password') }}</label><input class="form-control" id="admin_password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></div>
                <div class="col-12"><button class="btn btn-primary" type="submit">{{ __('Create Sabha administrator') }}</button></div>
            </form>
        @endif
    </section>

    <section>
        @php($settingLabels = [
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
        <h2 class="h5 mb-3">{{ __('Independent library settings') }}</h2>
        @if ($settingsStatus['complete'])
            <div class="alert alert-success" role="status">{{ __('Configuration complete for :name.', ['name' => $sabha->name]) }}</div>
        @else
            <div class="alert alert-warning" role="status">
                <strong>{{ __('Configuration incomplete for :name.', ['name' => $sabha->name]) }}</strong>
                @if ($settingsStatus['missing'] !== [])
                    <div>{{ __('Missing:') }} {{ collect($settingsStatus['missing'])->map(fn ($key) => __($settingLabels[$key] ?? $key))->implode(', ') }}</div>
                @endif
                @if ($settingsStatus['invalid'] !== [])
                    <div>{{ __('Invalid:') }} {{ collect($settingsStatus['invalid'])->map(fn ($key) => __($settingLabels[$key] ?? $key))->implode(', ') }}</div>
                @endif
            </div>
        @endif
        <p class="text-muted mb-0">{{ __('Independent library settings are managed by this Sabha administrator.') }}</p>
    </section>
@endsection