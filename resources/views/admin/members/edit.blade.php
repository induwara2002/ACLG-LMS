@extends('layouts.admin')

@section('title', __('Edit Member'))

@section('content')
@php($memberRoute = auth()->user()->hasRole('LIBRARIAN') ? 'librarian.members' : 'admin.members')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ __('Edit') }} {{ __('Member') }}</h1>
        <p class="page-subtitle">{{ __('Update member details') }}</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-info">{{ __('Member ID is generated automatically and cannot be edited manually.') }}</div>
        <form method="POST" action="{{ route($memberRoute . '.update', $member) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Member ID') }}</label>
                    <input type="text" class="form-control" value="{{ $member->member_id }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Full Name') }}</label>
                    <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name', $member->full_name) }}" required>
                    @error('full_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $member->email) }}" required>
                    @error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('NIC') }}</label>
                    <input type="text" name="nic" class="form-control @error('nic') is-invalid @enderror" value="{{ old('nic', $member->nic) }}" required>
                    @error('nic') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Phone') }}</label>
                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $member->phone) }}" required>
                    @error('phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('Address') }}</label>
                    <textarea name="address" rows="3" class="form-control @error('address') is-invalid @enderror">{{ old('address', $member->address) }}</textarea>
                    @error('address') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Membership Type') }}</label>
                    <select name="membership_type_id" class="form-select @error('membership_type_id') is-invalid @enderror" required>
                        @foreach ($membershipTypes as $type)
                            <option value="{{ $type->id }}" {{ old('membership_type_id', $member->membership_type_id) == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('membership_type_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Status') }}</label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                        @foreach (['PENDING', 'ACTIVE', 'REJECTED', 'EXPIRED', 'SUSPENDED'] as $status)
                            <option value="{{ $status }}" {{ old('status', $member->status) == $status ? 'selected' : '' }}>{{ __($status) }}</option>
                        @endforeach
                    </select>
                    @error('status') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-12"><div class="alert alert-secondary mb-0">{{ __('Registration and membership dates are historical records. Renewals are handled through the membership action.') }}</div></div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">{{ __('Update') }} {{ __('Member') }}</button>
                <a href="{{ route($memberRoute . '.show', $member) }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
