<button type="{{ $type ?? 'button' }}" class="btn {{ $variant ?? 'btn-primary' }} {{ $class ?? '' }}">
    {{ $slot }}
</button>
