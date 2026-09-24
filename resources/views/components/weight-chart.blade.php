@props(['measurements' => []])
@php
    $values = collect($measurements)->sortBy('measured_on')->pluck('weight_kg')->filter()->map(fn ($v) => (float) $v)->values();
@endphp
@if($values->count() > 1)
    @php $min = $values->min() - 2; $range = max(1, $values->max() - $min); $count = $values->count() - 1; @endphp
    <svg class="progress-chart" viewBox="0 0 600 220" role="img" aria-label="روند تغییر وزن">
        <polyline points="@foreach($values as $i => $v){{ 20 + ($i / $count) * 560 }},{{ 195 - (($v - $min) / $range) * 165 }} @endforeach" fill="none" stroke="var(--brand)" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
        @foreach($values as $i => $v)
            <circle cx="{{ 20 + ($i / $count) * 560 }}" cy="{{ 195 - (($v - $min) / $range) * 165 }}" r="6" fill="var(--brand-accent)"><title>{{ $v }} کیلو</title></circle>
        @endforeach
    </svg>
@else
    <x-empty-state title="برای نمودار حداقل دو وزن لازم است"/>
@endif
