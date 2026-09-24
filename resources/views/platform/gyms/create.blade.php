<x-layouts.platform :title="__('ui.create_gym')" :breadcrumb="__('ui.create_gym')">
    <div class="mb-6"><h1 class="text-3xl font-black">{{ __('ui.create_gym') }}</h1><p class="mt-2 text-slate-500">{{ __('ui.create_gym_help') }}</p></div>
    <form method="POST" action="{{ route('platform.gyms.store') }}" class="space-y-6">@csrf
        @if($errors->any())
            <x-alert id="form-errors" class="border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100" tabindex="-1" x-init="$el.focus()">
                <p class="font-black">{{ __('ui.form_has_errors') }}</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif
        <x-card><h2 class="mb-5 text-lg font-black">{{ __('ui.gym_information') }}</h2>@include('platform.gyms._form', ['gym' => null])</x-card>
        <x-card><h2 class="mb-5 text-lg font-black">{{ __('ui.owner_information') }}</h2><div class="grid gap-5 md:grid-cols-2">
            <x-input name="owner_name" :label="__('ui.owner_name')" :value="old('owner_name')" required />
            <x-input name="owner_email" type="email" :label="__('ui.owner_email')" :value="old('owner_email')" required dir="ltr" />
        </div></x-card>
        <x-card><h2 class="mb-2 text-lg font-black">{{ __('ui.initial_modules') }}</h2><p class="mb-5 text-sm text-slate-500">{{ __('ui.base_modules_forced') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">@foreach($modules as $module)
                <x-checkbox name="modules[]" value="{{ $module['key'] }}" :label="$module['name'].' ('.$module['key'].')'" :checked="in_array($module['key'], (array) old('modules', ['gyms','dashboard','settings']), true)" />
            @endforeach</div>
            @error('modules')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('modules.*')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
        </x-card>
        <x-button type="submit">{{ __('ui.create_gym') }}</x-button>
    </form>
</x-layouts.platform>
