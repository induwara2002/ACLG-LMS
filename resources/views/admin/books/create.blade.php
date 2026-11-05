@extends('layouts.admin')

@section('title', __('Create Book'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ __('Create Book') }}</h1>
        <p class="page-subtitle">{{ __('Add a new book to the library catalog') }}</p>
    </div>
    <a href="{{ route('admin.books.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.books.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.books.registration-form')
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Save Book') }}</button>
                <a href="{{ route('admin.books.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
