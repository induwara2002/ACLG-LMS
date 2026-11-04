<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        @if (!empty($subtitle))
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if (!empty($action))
        <div>{{ $action }}</div>
    @endif
</div>
