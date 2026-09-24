<x-layouts.guest :title="__('ui.no_gym_title')">
    <x-card>
        <div class="space-y-5 text-center">
            <div class="mx-auto grid size-14 place-items-center rounded-full bg-amber-100 text-2xl dark:bg-amber-950">!</div>
            <h1 class="text-2xl font-black">{{ __('ui.no_gym_title') }}</h1>
            <p class="leading-8 text-slate-500">{{ __('ui.no_gym_body') }}</p>
            <x-button disabled class="w-full">{{ __('ui.create_gym_future') }}</x-button>
            <form method="POST" action="{{ route('account.logout') }}">
                @csrf
                <x-button type="submit" variant="secondary" class="w-full">{{ __('ui.logout') }}</x-button>
            </form>
        </div>
    </x-card>
</x-layouts.guest>
