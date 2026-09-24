<x-layouts.guest :title="__('ui.forgot_password')">
    <x-card>
        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf
            <p class="section-kicker">Account recovery</p><h1 class="page-title">{{ __('ui.forgot_password') }}</h1>
            <p class="page-subtitle">{{ __('ui.reset_help') }}</p>
            @if(session('status')) <x-alert>{{ session('status') }}</x-alert> @endif
            <x-input name="email" type="email" :label="__('ui.email')" :value="old('email')" required autofocus />
            <x-button type="submit" class="w-full">{{ __('ui.send_reset_link') }}</x-button>
        </form>
    </x-card>
</x-layouts.guest>
