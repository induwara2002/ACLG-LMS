@extends('layouts.member')

@section('title', $book->title)

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">{{ $book->title }}</h1>
            <p class="page-subtitle">{{ __('Catalog details and current availability') }}</p>
        </div>
        <a href="{{ route('member.books.search') }}" class="btn btn-outline-secondary">{{ __('Back to books') }}</a>
    </div>
    <div class="card">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('ISBN') }}</dt><dd class="col-sm-9">{{ $book->isbn ?: '—' }}</dd>
                <dt class="col-sm-3">{{ __('Author') }}</dt><dd class="col-sm-9">{{ $book->author?->name ?? '—' }}</dd>
                <dt class="col-sm-3">{{ __('Publisher') }}</dt><dd class="col-sm-9">{{ $book->publisher?->name ?? '—' }}</dd>
                <dt class="col-sm-3">{{ __('Category') }}</dt><dd class="col-sm-9">{{ $book->category?->name ?? '—' }}</dd>
                <dt class="col-sm-3">{{ __('DDC') }}</dt><dd class="col-sm-9">{{ $book->ddc_number ?: '—' }}</dd>
                <dt class="col-sm-3">{{ __('Published year') }}</dt><dd class="col-sm-9">{{ $book->publication_year ?: '—' }}</dd>
                <dt class="col-sm-3">{{ __('Edition') }}</dt><dd class="col-sm-9">{{ $book->edition ?: '—' }}</dd>
                <dt class="col-sm-3">{{ __('Availability') }}</dt>
                <dd class="col-sm-9">{{ $book->inventories->sum('available_quantity') > 0 ? __('Available at your library') : __('Currently unavailable') }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection