@extends('layouts.admin')

@section('title', __('Edit Book'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ __('Edit Book') }}</h1>
        <p class="page-subtitle">{{ __('Update book information') }}</p>
    </div>
    <a href="{{ route('admin.books.show', $book) }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.books.update', $book) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('admin.books.form')
            <button type="submit" class="btn btn-primary">{{ __('Update Book') }}</button>
        </form>
    </div>
</div>
@endsection
