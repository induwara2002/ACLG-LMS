@extends('layouts.admin')

@section('title', __('Audit Logs'))

@section('content')
    <h1 class="page-title">{{ __('Audit Logs') }}</h1>

    <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-3 align-items-end mb-4">
        <div class="col-md-4">
            <label class="form-label" for="search">{{ __('Action or user') }}</label>
            <input class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="action">{{ __('Action') }}</label>
            <input class="form-control" id="action" name="action" value="{{ $filters['action'] ?? '' }}" maxlength="100">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="date_from">{{ __('From') }}</label>
            <input class="form-control" id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="date_to">{{ __('To') }}</label>
            <input class="form-control" id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div class="col-md-1 d-grid">
            <button class="btn btn-primary" type="submit">{{ __('Filter') }}</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th scope="col">{{ __('Time') }}</th>
                    <th scope="col">{{ __('Action') }}</th>
                    <th scope="col">{{ __('User') }}</th>
                    <th scope="col">{{ __('Record') }}</th>
                    <th scope="col">{{ __('IP address') }}</th>
                    <th scope="col">{{ __('Changes') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->user?->name ?? 'System / removed user' }}</td>
                        <td>{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
                        <td>{{ $log->ip_address }}</td>
                        <td>
                            @if ($log->old_values || $log->new_values)
                                <details>
                                    <summary>{{ __('View') }}</summary>
                                    <pre class="small mb-0">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">{{ __('No audit records found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
@endsection