@extends(auth()->user()?->hasRole('MEMBER') ? 'layouts.member' : 'layouts.admin')

@section('title', __('Change Password'))

@section('content')
    <h1 class="page-title mb-4">{{ __('Change Password') }}</h1>
    <form method="POST" action="{{ auth()->user()?->hasRole('MEMBER') ? route('member.password.update') : route('account.password.update') }}" class="row g-3" style="max-width: 36rem">
        @csrf
        @method('PUT')
        <div class="col-12">
            <label class="form-label" for="current_password">{{ __('Current password') }}</label>
            <input class="form-control" id="current_password" type="password" name="current_password" autocomplete="current-password" required>
            @error('current_password')<div class="text-danger mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="password">{{ __('New password') }}</label>
            <input class="form-control" id="password" type="password" name="password" autocomplete="new-password" minlength="12" required>
            @error('password')<div class="text-danger mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="password_confirmation">{{ __('Confirm new password') }}</label>
            <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required>
        </div>
        <div class="col-12"><button class="btn btn-primary" type="submit">{{ __('Update Password') }}</button></div>
    </form>
@endsection 