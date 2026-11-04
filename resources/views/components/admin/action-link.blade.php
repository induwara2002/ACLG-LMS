@props(['href', 'title', 'description'])

<a class="ps-quick-action-link" href="{{ $href }}">
    <span class="ps-quick-action-icon">{{ $icon }}</span>
    <span><strong>{{ $title }}</strong><small>{{ $description }}</small></span>
    <b aria-hidden="true">→</b>
</a>
