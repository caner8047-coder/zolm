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
        'check-circle' => '<circle cx="12" cy="12" r="10" /><path d="m9 12 2 2 4-4" />',
        'check-circle-2' => '<circle cx="12" cy="12" r="10" /><path d="m8.5 12.5 2.5 2.5 4.5-5" />',
        'check-square' => '<rect x="3" y="3" width="18" height="18" rx="2" /><path d="m9 12 2 2 4-4" />',
        'clock' => '<circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" />',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8.5" cy="9.5" r="1.5" /><path d="m21 15-4.5-4.5a2 2 0 0 0-2.83 0L6 18" />',
        'inbox' => '<path d="M3 13h5l2 3h4l2-3h5" /><path d="M5 13V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7" /><path d="M3 13v5a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5" />',
        'info' => '<circle cx="12" cy="12" r="10" /><path d="M12 16v-4" /><path d="M12 8h.01" />',
        'message-circle' => '<path d="M7.5 7.5h9" /><path d="M7.5 11h6" /><path d="M12 3C6.48 3 2 6.94 2 11.8c0 2.54 1.22 4.83 3.18 6.44L4 21l3.31-1.66A11.8 11.8 0 0 0 12 20.6c5.52 0 10-3.94 10-8.8S17.52 3 12 3Z" />',
        'message-square' => '<path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z" /><path d="M8 8h8" /><path d="M8 12h5" />',
        'message-square-dashed' => '<path d="M7 8h8" stroke-dasharray="3 3" /><path d="M7 12h10" /><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z" stroke-dasharray="4 3" />',
        'package' => '<path d="m7.5 4.27 9 5.15" /><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" /><path d="M3.29 7 12 12l8.71-5" /><path d="M12 22V12" />',
        'search' => '<circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" />',
        'shopping-bag' => '<path d="M6 8h12l-1 11a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2Z" /><path d="M9 8V6a3 3 0 0 1 6 0v2" />',
        'upload-cloud' => '<path d="M12 16V8" /><path d="m8.5 11.5 3.5-3.5 3.5 3.5" /><path d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25" /><path d="M8 16h8" />',
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
