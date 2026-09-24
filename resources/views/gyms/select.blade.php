<x-layouts.guest :title="__('ui.select_gym')">
    <x-card>
        <div class="space-y-5">
            <div>
                <h1 class="text-2xl font-black">{{ __('ui.select_gym') }}</h1>
                <p class="mt-2 text-sm leading-7 text-slate-500">{{ __('ui.select_gym_help') }}</p>
            </div>
            @if(session('warning')) <x-alert>{{ session('warning') }}</x-alert> @endif
            @if($gyms->isEmpty())
                <x-empty-state :title="__('ui.no_gym_title')" :description="__('ui.no_gym_body')" />
            @else
                <form method="POST" action="{{ route('account.gyms.switch') }}" class="space-y-4">
                    @csrf
                    <x-select name="gym_id" :label="__('ui.current_gym')" required>
                        @foreach($gyms as $gym)
                            <option value="{{ $gym->id }}">{{ $gym->name }}</option>
                        @endforeach
                    </x-select>
                    <x-button type="submit" class="w-full">{{ __('ui.continue') }}</x-button>
                </form>
            @endif
            <form method="POST" action="{{ route('account.logout') }}">
                @csrf
                <x-button type="submit" variant="secondary" class="w-full">{{ __('ui.logout') }}</x-button>
            </form>
        </div>
    </x-card>
</x-layouts.guest>
