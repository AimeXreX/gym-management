@props(['variant'=>'primary','href'=>null,'type'=>'button','icon'=>null])
@php($classes='btn btn-'.$variant)
@if($href)<a href="{{ $href }}" {{ $attributes->class($classes) }}>@if($icon)<x-icon :name="$icon"/>@endif{{ $slot }}</a>
@else<button type="{{ $type }}" {{ $attributes->class($classes) }}>@if($icon)<x-icon :name="$icon"/>@endif{{ $slot }}</button>@endif
