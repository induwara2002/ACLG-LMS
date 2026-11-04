@props(['status'])

@php
    $normalizedStatus = strtoupper((string) $status);
    $statusClass = match ($normalizedStatus) {
        'ISSUED', 'ACTIVE', 'RENEWED' => 'active',
        'RETURNED' => 'returned',
        'OVERDUE', 'LOST', 'DAMAGED' => 'overdue',
        default => 'neutral',
    };
@endphp

<span class="ps-status-badge ps-status-{{ $statusClass }}">{{ __($normalizedStatus) }}</span>
