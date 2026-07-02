@props([
    'data', 
    'width' => 100, 
    'height' => 30, 
    'color' => 'text-indigo-500', 
    'strokeWidth' => 2,
    'fill' => false,
    'inverse' => false
])

@php
    $count = count($data);
    $pointsStr = '';
    
    if ($count > 1) {
        $min = min($data);
        $max = max($data);
        $diff = $max - $min;
        if ($diff == 0) $diff = 1;
        
        $points = [];
        $stepX = $width / ($count - 1);
        
        foreach (array_values($data) as $i => $val) {
            $x = $i * $stepX;
            // Normalize y between 2 and height-2
            $ratio = ($val - $min) / $diff;
            if ($inverse) $ratio = 1 - $ratio;
            $y = $height - 2 - ($ratio * ($height - 4));
            $points[] = round($x, 1) . ',' . round($y, 1);
        }
        
        $pointsStr = implode(' ', $points);
        
        if ($fill) {
            $fillPointsStr = "0," . $height . " " . $pointsStr . " " . $width . "," . $height;
        }
    }
@endphp

@if($count > 1)
<svg width="100%" height="100%" viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" class="{{ $color }} overflow-visible">
    @if($fill)
        <polygon points="{{ $fillPointsStr }}" fill="currentColor" fill-opacity="0.1" />
    @endif
    <polyline points="{{ $pointsStr }}" fill="none" stroke="currentColor" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round" />
    <!-- Noktalar -->
    @foreach(array_values($data) as $i => $val)
        @php
            $x = $i * $stepX;
            $ratio = ($val - $min) / $diff;
            if ($inverse) $ratio = 1 - $ratio;
            $y = $height - 2 - ($ratio * ($height - 4));
        @endphp
        <circle cx="{{ round($x, 1) }}" cy="{{ round($y, 1) }}" r="2.5" fill="white" stroke="currentColor" stroke-width="1.5" />
    @endforeach
</svg>
@else
<div class="flex h-full w-full items-center justify-center text-[10px] text-slate-400">Yetersiz veri</div>
@endif
