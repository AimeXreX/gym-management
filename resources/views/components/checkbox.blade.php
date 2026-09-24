@props(['label','name','value'=>'1','checked'=>false])
<label class="inline-flex cursor-pointer items-center gap-3 text-sm font-medium"><input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($checked) {{ $attributes->class('size-[1.125rem] rounded border-slate-300 accent-indigo-600') }}><span>{{ $label }}</span></label>
