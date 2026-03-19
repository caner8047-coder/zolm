@props([
    'title' => null,
    'eyebrow' => null,
    'description' => null,
    'padding' => 'p-4 lg:p-6',
    'headerPadding' => null,
    'bodyPadding' => null,
    'headerClass' => '',
    'bodyClass' => '',
    'variant' => 'default',
])

@php
    $sectionClasses = match ($variant) {
        'classic' => 'rounded-xl border border-gray-200 bg-white shadow-sm',
        'orders' => 'rounded-lg border border-gray-200 bg-white shadow shadow-gray-100/70',
        default => 'rounded-[28px] border border-slate-200 bg-white shadow-sm',
    };

    $eyebrowVariant = in_array($variant, ['classic', 'orders'], true) ? 'classic' : 'default';
    $titleClasses = match ($variant) {
        'classic' => (($eyebrow ? 'mt-2 ' : '') . 'text-lg lg:text-xl font-semibold text-gray-900'),
        'orders' => (($eyebrow ? 'mt-2 ' : '') . 'text-xl lg:text-2xl font-bold text-gray-900'),
        default => (($eyebrow ? 'mt-3 ' : '') . 'text-xl lg:text-2xl font-bold text-slate-900'),
    };
    $descriptionClasses = match ($variant) {
        'classic' => 'mt-2 text-sm text-gray-500',
        'orders' => 'mt-2 text-sm lg:text-base text-gray-500',
        default => 'mt-2 text-[10px] sm:text-sm leading-4 sm:leading-5 text-slate-500',
    };

    $resolvedHeaderPadding = $headerPadding ?: $padding;
    $resolvedBodyPadding = $bodyPadding ?: $padding;
@endphp

<section {{ $attributes->merge([
    'class' => $sectionClasses,
]) }}>
    @if($eyebrow || $title || $description || isset($header))
        <div class="{{ $resolvedHeaderPadding }} {{ $headerClass }}">
            @if(isset($header))
                {{ $header }}
            @else
                @if($eyebrow)
                    <x-zolm.eyebrow :variant="$eyebrowVariant">{{ $eyebrow }}</x-zolm.eyebrow>
                @endif

                @if($title)
                    <h2 class="{{ $titleClasses }}">{{ $title }}</h2>
                @endif

                @if($description)
                    <p class="{{ $descriptionClasses }}">{{ $description }}</p>
                @endif
            @endif
        </div>
    @endif

    <div class="{{ $resolvedBodyPadding }} {{ $bodyClass }}">
        {{ $slot }}
    </div>
</section>
