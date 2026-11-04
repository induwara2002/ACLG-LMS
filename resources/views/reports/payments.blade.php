@extends($routePrefix === 'librarian' ? 'layouts.app' : 'layouts.admin')
@section('title', __('Payment Report'))
@section('content')
@include('reports._table')
@endsection
