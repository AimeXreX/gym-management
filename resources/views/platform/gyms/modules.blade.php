<x-layouts.platform :title="__('ui.manage_modules')" :breadcrumb="__('ui.manage_modules')">
    <div class="mb-6"><h1 class="text-3xl font-black">{{ __('ui.manage_modules') }}: {{ $gym->name }}</h1><p class="mt-2 text-slate-500">{{ __('ui.base_modules_forced') }}</p></div>
    <form method="POST" action="{{ route('platform.gyms.modules.update', $gym) }}" class="space-y-4">@csrf @method('PUT')
        @foreach($definitions as $key => $definition)
            @php($entitlement = $entitlements->get($key))
            @php($base = in_array($key, ['gyms','dashboard','settings'], true))
            <x-card>
                <div class="flex items-start justify-between gap-4">
                    <div><h2 class="font-black">{{ $definition['name'] }} <span class="font-mono text-xs text-slate-500">{{ $key }}</span></h2><p class="mt-2 text-sm text-slate-500">{{ $definition['description'] }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ __('ui.dependencies') }}: {{ implode(', ', $definition['dependencies'] ?: [__('ui.none')]) }}</p>
                        @if($entitlement?->pivot->enabled_at)<p class="mt-1 text-xs text-slate-500">{{ __('ui.enabled_at') }}: {{ $entitlement->pivot->enabled_at }}</p>@endif
                    </div>
                    @if($base)<input type="hidden" name="modules[]" value="{{ $key }}">@endif
                    <x-checkbox name="modules[]" value="{{ $key }}" :label="$entitlement?->pivot->enabled ? __('ui.active') : __('ui.inactive')" :checked="$base || (bool) $entitlement?->pivot->enabled" :disabled="$base" />
                </div>
            </x-card>
        @endforeach
        @error('modules')<x-alert>{{ $message }}</x-alert>@enderror
        <x-button type="submit">{{ __('ui.save_module_changes') }}</x-button>
    </form>
</x-layouts.platform>
