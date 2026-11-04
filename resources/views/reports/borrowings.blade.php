@extends($routePrefix === 'librarian' ? 'layouts.app' : 'layouts.admin')
@section('title', __('Borrowing Report'))
@section('content')
@include('reports._table')
@endsection
