<aside class="sidebar d-md-block" data-sidebar>
    <div class="p-3 border-bottom border-secondary-subtle">
        <h5 class="mb-0 text-white">{{ $title ?? 'Navigation' }}</h5>
    </div>
    <nav class="nav flex-column p-3 gap-1">
        {{ $slot }}
    </nav>
</aside>
