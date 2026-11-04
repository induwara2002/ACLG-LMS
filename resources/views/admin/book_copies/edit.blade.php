@extends('layouts.admin')
@section('title', __('Edit Book Copy'))
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="page-title">{{ __('Edit Book Copy') }}</h1><p class="page-subtitle">{{ __('Update physical copy details') }}</p></div><a href="{{ route('admin.book-copies.show', $bookCopy) }}" class="btn btn-outline-secondary">{{ __('Back') }}</a></div>
<div class="card"><div class="card-body"><form method="POST" action="{{ route('admin.book-copies.update', $bookCopy) }}">@csrf @method('PUT') @include('admin.book_copies.form')<button type="submit" class="btn btn-primary">{{ __('Update') }} {{ __('Copy') }}</button></form></div></div>
@endsection