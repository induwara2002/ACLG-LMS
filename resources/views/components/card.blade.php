<div class="card {{ $class ?? '' }}">
    @if (!empty($header))
        <div class="card-header {{ $headerClass ?? '' }}">
            {{ $header }}
        </div>
    @endif
    <div class="card-body {{ $bodyClass ?? '' }}">
        {{ $slot }}
    </div>
</div>
