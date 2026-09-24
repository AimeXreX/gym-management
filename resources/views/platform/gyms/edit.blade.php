<x-layouts.platform :title="__('ui.edit_gym')" :breadcrumb="__('ui.edit_gym')">
    <div class="mb-6"><h1 class="text-3xl font-black">{{ __('ui.edit_gym') }}: {{ $gym->name }}</h1><p class="mt-2 text-slate-500">{{ __('ui.owner') }}: {{ $gym->owner?->name }} — {{ $gym->owner?->email }}</p></div>
    <form method="POST" action="{{ route('platform.gyms.update', $gym) }}" class="space-y-6">@csrf @method('PUT')
        <x-card>@include('platform.gyms._form', ['gym' => $gym])</x-card>
        <div class="flex gap-3"><x-button type="submit">{{ __('ui.save_changes') }}</x-button><x-button variant="secondary" :href="route('platform.gyms.modules.edit', $gym)">{{ __('ui.manage_modules') }}</x-button></div>
    </form>
</x-layouts.platform>
