@props(['label','name','value'=>null,'rows'=>4])
<div><label for="{{ $name }}" class="label">{{ $label }}</label><textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->class(['field resize-y','!border-red-500'=>$errors->has($name)]) }}>{{ $value }}</textarea>@error($name)<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror</div>
