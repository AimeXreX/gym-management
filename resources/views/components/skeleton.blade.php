@props(['lines'=>3])
<div role="status" aria-label="{{ __('ui.loading') }}" {{ $attributes->class('space-y-3') }}>@for($i=0;$i<$lines;$i++)<div class="skeleton h-4 {{ $i===$lines-1?'w-2/3':'w-full' }}"></div>@endfor<span class="sr-only">{{ __('ui.loading') }}</span></div>
