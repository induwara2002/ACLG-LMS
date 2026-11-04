@props(['label', 'value', 'trend', 'icon', 'tone', 'href', 'inverse' => false])

<a class="ps-stat-card ps-stat-{{ $tone }}" href="{{ $href }}">
    <div class="ps-stat-card-top">
        <span>{{ $label }}</span>
        <span class="ps-stat-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                @switch($icon)
                    @case('members')
                        <circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 11a3 3 0 1 0 0-6m1 9a5 5 0 0 1 4.5 5"></path>
                        @break
                    @case('books')
                        <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21V5.5Z"></path><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20M8 7h8M8 10h8"></path>
                        @break
                    @case('issued')
                        <path d="M5 12h14m-5-5 5 5-5 5"></path><path d="M4 5h6M4 19h6"></path>
                        @break
                    @case('borrowed')
                        <path d="M4 6h16M4 12h16M4 18h11"></path><circle cx="19" cy="18" r="2"></circle>
                        @break
                    @default
                        <circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path>
                @endswitch
            </svg>
        </span>
    </div>
    <strong class="ps-stat-value">{{ number_format($value) }}</strong>
    <span class="ps-stat-trend {{ $trend['direction'] === 0 ? 'is-flat' : (($trend['direction'] < 0) === $inverse ? 'is-up' : 'is-down') }}">
        @if ($trend['percent'] !== null)
            <span aria-hidden="true">{{ $trend['direction'] > 0 ? '↑' : ($trend['direction'] < 0 ? '↓' : '→') }}</span>
            {{ abs($trend['percent']) }}%
        @else
            {{ $trend['current'] > 0 ? 'New' : '—' }}
        @endif
        <span>{{ __('vs. last month') }}</span>
    </span>
</a>
