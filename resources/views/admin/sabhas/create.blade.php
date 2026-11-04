@extends('layouts.admin')

@section('title', __('Add Pradeshiya Sabha'))

@section('content')
    <h1 class="page-title mb-4">{{ __('Add Pradeshiya Sabha') }}</h1>
    <form method="POST" action="{{ route('admin.sabhas.store') }}" class="row g-3">
        @csrf
        <div class="col-md-6"><label class="form-label" for="name">{{ __('Name') }}</label><input class="form-control" id="name" name="name" value="{{ old('name') }}" required maxlength="255"></div>
        <div class="col-md-3"><label class="form-label" for="code">{{ __('Code') }}</label><input class="form-control" id="code" name="code" value="{{ old('code') }}" required maxlength="12"></div>
        <div class="col-md-9"><label class="form-label" for="address">{{ __('Address') }}</label><input class="form-control" id="address" name="address" value="{{ old('address') }}" maxlength="5000"></div>
        <div class="col-md-4"><label class="form-label" for="phone">{{ __('Phone') }}</label><input class="form-control" id="phone" name="phone" value="{{ old('phone') }}" maxlength="50"></div>
        <div class="col-md-5"><label class="form-label" for="email">{{ __('Email') }}</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" maxlength="255"></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">{{ __('Create Sabha') }}</button><a class="btn btn-outline-secondary" href="{{ route('admin.sabhas.index') }}">{{ __('Cancel') }}</a></div>
    </form>
@endsection