@extends($routePrefix === 'librarian' ? 'layouts.app' : 'layouts.admin')
@section('title', __('Overdue Report'))
@section('content')
@include('reports._table')
@endsection
