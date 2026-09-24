@props(['label','name','hint'=>null])
<div><label for="{{ $name }}" class="label">{{ $label }}</label><select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class('field') }}>{{ $slot }}</select>@if($hint&&!$errors->has($name))<p class="mt-2 text-xs muted">{{ $hint }}</p>@endif @error($name)<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror</div>
