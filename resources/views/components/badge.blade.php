@props(['tone'=>'brand'])
@php($tones=['brand'=>'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300','success'=>'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300','warning'=>'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300','neutral'=>'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300'])
<span {{ $attributes->class('inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset ring-black/5 '.$tones[$tone]) }}>{{ $slot }}</span>
