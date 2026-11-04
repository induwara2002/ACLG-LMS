@extends('layouts.admin')

@section('title', __('Reports'))

@section('content')
<div class="report-page-header"><div><h1 class="page-title">{{ __('Reports') }}</h1><p class="page-subtitle">{{ __('Database-backed operational and financial reports.') }}</p></div></div><div class="row g-3">
    @foreach ($reports as $report)
        <div class="col-md-6 col-xl-4"><a class="card h-100 p-3 text-decoration-none report-card" href="{{ route($routePrefix.'.reports.show', $report) }}"><h2 class="h5 text-capitalize">{{ str_replace('-', ' ', $report) }}</h2><span class="text-muted">{{ __('Open report and set filters') }}</span></a></div>
    @endforeach
</div>
@endsection
