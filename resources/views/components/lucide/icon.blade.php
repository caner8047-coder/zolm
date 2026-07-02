@props(['name'])

@php
    $icons = [
        'activity' => '<path d="M22 12h-4l-3 9-6-18-3 9H2" />',
        'alert-circle' => '<circle cx="12" cy="12" r="10" /><path d="M12 8v4" /><path d="M12 16h.01" />',
        'alert-triangle' => '<path d="m10.29 3.86-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.71-3.14l-8-14a2 2 0 0 0-3.42 0Z" /><path d="M12 9v4" /><path d="M12 17h.01" />',
        'arrow-left-right' => '<path d="M8 3 4 7l4 4" /><path d="M4 7h16" /><path d="m16 21 4-4-4-4" /><path d="M20 17H4" />',
        'arrow-up' => '<path d="m12 19 0-14" /><path d="m7 8 5-5 5 5" />',
        'banknote' => '<rect x="2" y="6" width="20" height="12" rx="2" /><circle cx="12" cy="12" r="2.5" /><path d="M6 12h.01" /><path d="M18 12h.01" />',
        'bar-chart-2' => '<line x1="18" x2="18" y1="20" y2="10" /><line x1="12" x2="12" y1="20" y2="4" /><line x1="6" x2="6" y1="20" y2="14" />',
        'badge-percent' => '<path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z" /><path d="m15 9-6 6" /><path d="M9 9h.01" /><path d="M15 15h.01" />',
        'boxes' => '<path d="M2.97 12.92 8 15.77l5-2.85" /><path d="m8 15.77 5-2.85 5.03 2.85" /><path d="M8 15.77v5.66" /><path d="M13 12.92v5.66" /><path d="M18.03 15.77v-5.66L13 7.26l-5.03 2.85v5.66" /><path d="M13 7.26V1.6L7.97 4.45v5.66" /><path d="M18.03 10.11 13 12.96l-5.03-2.85" />',
        'check' => '<path d="M20 6 9 17l-5-5" />',
        'check-circle' => '<circle cx="12" cy="12" r="10" /><path d="m9 12 2 2 4-4" />',
        'check-circle-2' => '<circle cx="12" cy="12" r="10" /><path d="m8.5 12.5 2.5 2.5 4.5-5" />',
        'check-square' => '<rect x="3" y="3" width="18" height="18" rx="2" /><path d="m9 12 2 2 4-4" />',
        'clock' => '<circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" />',
        'columns-3' => '<rect width="18" height="18" x="3" y="3" rx="2" /><path d="M9 3v18" /><path d="M15 3v18" />',
        'gauge' => '<path d="m12 14 4-4" /><path d="M3.34 19a10 10 0 1 1 17.32 0" />',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7" /><path d="M3 3v6h6" /><path d="M12 7v5l4 2" />',
        'heart' => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z" />',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8.5" cy="9.5" r="1.5" /><path d="m21 15-4.5-4.5a2 2 0 0 0-2.83 0L6 18" />',
        'inbox' => '<path d="M3 13h5l2 3h4l2-3h5" /><path d="M5 13V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7" /><path d="M3 13v5a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5" />',
        'info' => '<circle cx="12" cy="12" r="10" /><path d="M12 16v-4" /><path d="M12 8h.01" />',
        'line-chart' => '<path d="M3 3v18h18" /><path d="m19 9-5 5-4-4-3 3" />',
        'message-circle' => '<path d="M7.5 7.5h9" /><path d="M7.5 11h6" /><path d="M12 3C6.48 3 2 6.94 2 11.8c0 2.54 1.22 4.83 3.18 6.44L4 21l3.31-1.66A11.8 11.8 0 0 0 12 20.6c5.52 0 10-3.94 10-8.8S17.52 3 12 3Z" />',
        'message-square' => '<path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z" /><path d="M8 8h8" /><path d="M8 12h5" />',
        'message-square-dashed' => '<path d="M7 8h8" stroke-dasharray="3 3" /><path d="M7 12h10" /><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z" stroke-dasharray="4 3" />',
        'more-vertical' => '<circle cx="12" cy="5" r="1" /><circle cx="12" cy="12" r="1" /><circle cx="12" cy="19" r="1" />',
        'package' => '<path d="m7.5 4.27 9 5.15" /><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" /><path d="M3.29 7 12 12l8.71-5" /><path d="M12 22V12" />',
        'plus' => '<path d="M5 12h14" /><path d="M12 5v14" />',
        'radar' => '<path d="M19.07 4.93A10 10 0 1 0 12 22" /><path d="M13 13 19 7" /><path d="M13 13a2 2 0 1 0-2.83-2.83 2 2 0 0 0 2.83 2.83Z" /><path d="M19 13a7 7 0 1 0-7 7" /><path d="M19 13h3" /><path d="M19 13a3 3 0 0 1 3 3v1" />',
        'refresh-cw' => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8" /><path d="M21 3v5h-5" /><path d="M21 12a9 9 0 0 1-15 6.7L3 16" /><path d="M3 21v-5h5" />',
        'save' => '<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" /><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7" /><path d="M7 3v4a1 1 0 0 0 1 1h7" />',
        'external-link' => '<path d="M15 3h6v6" /><path d="M10 14 21 3" /><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />',
        'search' => '<circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" />',
        'shopping-bag' => '<path d="M6 8h12l-1 11a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2Z" /><path d="M9 8V6a3 3 0 0 1 6 0v2" />',
        'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />',
        'trending-up' => '<path d="m22 7-8.5 8.5-5-5L2 17" /><path d="M16 7h6v6" />',
        'trash-2' => '<path d="M3 6h18" /><path d="M8 6V4h8v2" /><path d="M19 6l-1 14H6L5 6" /><path d="M10 11v5" /><path d="M14 11v5" />',
        'upload-cloud' => '<path d="M12 16V8" /><path d="m8.5 11.5 3.5-3.5 3.5 3.5" /><path d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25" /><path d="M8 16h8" />',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />',
        'x' => '<path d="M18 6 6 18" /><path d="m6 6 12 12" />',
        'x-circle' => '<circle cx="12" cy="12" r="10" /><path d="M15 9 9 15" /><path d="m9 9 6 6" />',
    ];

    $markup = $icons[$name] ?? $icons['info'];
@endphp

<svg
    {{ $attributes->merge(['class' => 'h-4 w-4']) }}
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>
    {!! $markup !!}
</svg>
