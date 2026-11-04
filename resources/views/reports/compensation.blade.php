@extends($routePrefix === 'librarian' ? 'layouts.app' : 'layouts.admin')
@section('title', __('Compensation Report'))
@section('content')
@include('reports._table')
@endsection
