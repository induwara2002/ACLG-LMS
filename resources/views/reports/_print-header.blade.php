@php
    $filterLabels = [
        'search' => __('Search'),
        'sabha_id' => __('Pradeshiya Sabha'),
        'member_id' => __('Member'),
        'book_id' => __('Book'),
        'author_id' => __('Author'),
        'publisher_id' => __('Publisher'),
        'category_id' => __('Category'),
        'membership_type_id' => __('Membership type'),
        'status' => __('Status'),
        'payment_status' => __('Payment status'),
        'payment_type' => __('Payment type'),
        'fine_type' => __('Fine type'),
        'damage_type' => __('Damage type'),
        'availability' => __('Availability'),
        'ddc' => __('DDC'),
        'language' => __('Language'),
        'publication_year' => __('Publication year'),
        'date_from' => __('Date from'),
        'date_to' => __('Date to'),
        'registration_from' => __('Registration from'),
        'registration_to' => __('Registration to'),
        'expiry_from' => __('Expiry from'),
        'expiry_to' => __('Expiry to'),
    ];
    $printFilters = collect($filters)
        ->filter(fn ($value, $key) => isset($filterLabels[$key]) && $value !== null && $value !== '')
        ->map(function ($value, $key) use ($reportSabha, $members, $books, $membershipTypes) {
            return match ($key) {
                'sabha_id' => $reportSabha?->name ?? __('reports.all_pradeshiya_sabhas'),
                'member_id' => $members->firstWhere('id', (int) $value)?->full_name ?? (string) $value,
                'book_id' => $books->firstWhere('id', (int) $value)?->title ?? (string) $value,
                'membership_type_id' => $membershipTypes->firstWhere('id', (int) $value)?->name ?? (string) $value,
                default => is_scalar($value) ? (string) $value : '',
            };
        });
@endphp
<header class="report-print-header">
    <div class="report-print-identity">
        @if ($reportLogoAvailable && $reportSabha)
            <img class="report-print-logo" src="{{ route('sabhas.logo', $reportSabha) }}" alt="{{ __('Official logo for :name', ['name' => $reportSabha->name]) }}">
        @else
            <img class="report-print-logo" src="{{ asset('images/sri-lanka-state-emblem.png') }}" alt="{{ __('Sri Lankan State Emblem') }}">
        @endif
        <div class="report-print-institution">
            <h1>{{ $reportSabha ? ($reportSettings->get('name_si') ?: __('reports.sinhala_name_not_configured')) : __('reports.all_pradeshiya_sabhas') }}</h1>
            @if ($reportSabha)
                <p class="report-print-english-name">{{ $reportSabha->name }}</p>
            @endif
            <p class="report-print-library-name">
                {{ $reportSettings->get('library_name') ?: __('Library Management System') }}
            </p>
        </div>
    </div>
    <div class="report-print-heading">
        <h2>{{ $reportTitle }}</h2>
        <p>{{ __('reports.generated_at') }}: {{ now()->locale(app()->getLocale())->translatedFormat('j F Y, H:i') }}</p>
    </div>
    @if ($printFilters->isNotEmpty())
        <dl class="report-print-filters">
            @foreach ($printFilters as $key => $value)
                <div><dt>{{ $filterLabels[$key] }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
    @endif
</header>
