<nav class="navbar navbar-expand-lg navbar-custom mb-4">
    <div class="container-fluid">
        <button class="btn btn-outline-secondary d-md-none" type="button" data-sidebar-toggle>
            {{ __('Menu') }}
        </button>
        <div class="ms-auto d-flex align-items-center gap-3">
            {{ $slot }}
        </div>
    </div>
</nav>
