@extends('layouts.admin')
@section('title', __('Create Book Copy'))
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="page-title">{{ __('Create Book Copy') }}</h1><p class="page-subtitle">{{ __('Register a physical copy') }}</p></div><a href="{{ route('admin.book-copies.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a></div>
<div class="card"><div class="card-body"><form method="POST" action="{{ route('admin.book-copies.store') }}">@csrf @include('admin.book_copies.form')<button type="submit" class="btn btn-primary">{{ __('Save') }} {{ __('Copy') }}</button></form></div></div>
@endsection