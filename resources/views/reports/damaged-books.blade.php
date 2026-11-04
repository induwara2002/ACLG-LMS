@extends($routePrefix === 'librarian' ? 'layouts.app' : 'layouts.admin')
@section('title', __('Damaged Books Report'))
@section('content')
@include('reports._table')
@endsection
