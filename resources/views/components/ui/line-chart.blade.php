@props([
    'points' => [],   // [['label' => 'W1', 'value' => 82.4], ...]
    'unit' => 'kg',
])

@php
    $count = count($points);
    $values = array_column($points, 'value');

    // Geometry in a fixed viewBox; the SVG scales to its container.
    $width = 720;
    $height = 220;
    $padX = 34;
    $padY = 22;

    if ($count > 0) {
        $min = min($values);
        $max = max($values);
        // A flat line would divide by zero — give it breathing room instead.
        $span = ($max - $min) < 0.5 ? 1.0 : ($max - $min);
        $floor = $min - ($span * 0.15);
        $ceiling = $max + ($span * 0.15);
        $range = $ceiling - $floor;

        $x = fn (int $i) => $count === 1
            ? $width / 2
            : $padX + ($i * (($width - 2 * $padX) / ($count - 1)));
        $y = fn (float $v) => $padY + ((($ceiling - $v) / $range) * ($height - 2 * $padY));

        $coords = [];
        foreach ($values as $i => $value) {
            $coords[] = ['x' => round($x($i), 1), 'y' => round($y($value), 1), 'value' => $value];
        }
    }
@endphp

@if ($count === 0)
    <p class="py-10 text-center font-cond text-sm tracking-wide text-bone-dim uppercase">
        No weigh-ins recorded yet.
    </p>
@else
    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="h-auto w-full" role="img"
         aria-label="Weight over time, {{ $count }} {{ Str::plural('record', $count) }}">
        {{-- Baseline reference: where they started. --}}
        <line x1="{{ $padX }}" y1="{{ $coords[0]['y'] }}" x2="{{ $width - $padX }}" y2="{{ $coords[0]['y'] }}"
              stroke="currentColor" class="text-ink-3" stroke-width="1" stroke-dasharray="4 4" />

        @if ($count > 1)
            {{-- Soft fill under the line. --}}
            <polygon
                fill="url(#chartFill)"
                points="{{ $coords[0]['x'] }},{{ $height - $padY }} {{ collect($coords)->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }} {{ $coords[$count - 1]['x'] }},{{ $height - $padY }}" />

            <polyline fill="none" stroke="currentColor" class="text-gold" stroke-width="2.5"
                      stroke-linejoin="round" stroke-linecap="round"
                      points="{{ collect($coords)->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }}" />
        @endif

        <defs>
            <linearGradient id="chartFill" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stop-color="#c9a24b" stop-opacity="0.22" />
                <stop offset="100%" stop-color="#c9a24b" stop-opacity="0" />
            </linearGradient>
        </defs>

        @foreach ($coords as $index => $point)
            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="{{ $index === $count - 1 ? 5 : 3.5 }}"
                    fill="{{ $index === $count - 1 ? '#e5c877' : '#c9a24b' }}" />
            <text x="{{ $point['x'] }}" y="{{ $height - 4 }}" text-anchor="middle"
                  class="fill-bone-dim" style="font-family: 'Barlow Condensed'; font-size: 13px;">
                {{ $points[$index]['label'] }}
            </text>
        @endforeach

        {{-- Label only the first and last values, so the line stays readable. --}}
        <text x="{{ $coords[0]['x'] }}" y="{{ max($coords[0]['y'] - 12, 14) }}" text-anchor="middle"
              class="fill-bone-dim" style="font-family: 'Oswald'; font-size: 14px;">
            {{ number_format($coords[0]['value'], 1) }}
        </text>

        @if ($count > 1)
            <text x="{{ $coords[$count - 1]['x'] }}" y="{{ max($coords[$count - 1]['y'] - 12, 14) }}"
                  text-anchor="middle" class="fill-gold-bright"
                  style="font-family: 'Oswald'; font-size: 15px; font-weight: 600;">
                {{ number_format($coords[$count - 1]['value'], 1) }}{{ $unit }}
            </text>
        @endif
    </svg>
@endif
