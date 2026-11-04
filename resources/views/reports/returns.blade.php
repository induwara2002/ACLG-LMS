@extends($routePrefix === 'librarian' ? 'layouts.app' : 'layouts.admin')
@section('title', __('Returns Report'))
@section('content')
@include('reports._table')
@endsection
