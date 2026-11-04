@extends($routePrefix === 'librarian' ? 'layouts.app' : 'layouts.admin')
@section('title', __('Fine Report'))
@section('content')
@include('reports._table')
@endsection
