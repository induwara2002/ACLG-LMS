@extends('layouts.admin')

@section('title', __('Pradeshiya Sabha Management'))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title">{{ __('Pradeshiya Sabha Management') }}</h1>
        <a class="btn btn-primary" href="{{ route('admin.sabhas.create') }}">{{ __('Add Sabha') }}</a>
    </div>

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th scope="col">{{ __('Logo') }}</th>
                    <th scope="col">{{ __('Pradeshiya Sabha') }}</th>
                    <th scope="col">{{ __('Active') }}</th>
                    <th scope="col" id="admin-assignments">{{ __('Administrator') }}</th>
                    <th scope="col">{{ __('Members') }}</th>
                    <th scope="col" id="library-records">{{ __('Library books') }}</th>
                    <th scope="col">{{ __('Active borrowings') }}</th>
                    <th scope="col">{{ __('Overdue') }}</th>
                    <th scope="col">{{ __('Payments') }}</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sabhas as $sabha)
                    <tr>
                        <td>
                            @if ($sabha->logo_path)
                                <img src="{{ route('sabhas.logo', $sabha) }}" alt="{{ $sabha->name }} logo" width="48" height="48" class="object-fit-contain">
                            @else
                                <span class="text-muted">{{ __('Not uploaded') }}</span>
                            @endif
                        </td>
                        <td>{{ $sabha->name }}</td>
                        <td>{{ $sabha->is_active ? 'Active' : 'Inactive' }}</td>
                        <td>
                            @if ($sabha->adminAssignment?->is_active && $sabha->adminAssignment?->user?->is_active)
                                {{ __('Active') }}
                            @else
                                {{ __('Not configured') }}
                            @endif
                        </td>
                        <td>{{ $sabha->members_count }}</td>
                        <td>{{ $sabha->books_count }}</td>
                        <td>{{ $sabha->active_borrowings_count }}</td>
                        <td>{{ $sabha->overdue_count }}</td>
                        <td>{{ $sabha->payments_count }}</td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.sabhas.dashboard', $sabha) }}">{{ __('View Sabha Dashboard') }}</a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.sabhas.show', $sabha) }}">{{ __('Manage') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $sabhas->links() }}
@endsection