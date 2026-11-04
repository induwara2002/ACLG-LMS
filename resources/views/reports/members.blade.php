@extends($routePrefix === 'librarian' ? 'layouts.app' : 'layouts.admin')
@section('title', __('Members Report'))
@section('content')
@include('reports._table')
@endsection
