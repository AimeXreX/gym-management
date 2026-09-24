<x-layouts.guest :title="__('ui.login')">
    <x-card>
        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
            @csrf
            <div>
                <p class="section-kicker">Welcome back</p><h1 class="page-title">{{ __('ui.login') }}</h1>
                <p class="page-subtitle">{{ __('ui.login_help') }}</p>
            </div>
            <x-input name="email" type="email" :label="__('ui.email')" :value="old('email')" required autofocus autocomplete="email" />
            <x-input name="password" type="password" :label="__('ui.password')" required autocomplete="current-password" />
            <div class="flex items-center justify-between gap-4">
                <x-checkbox name="remember" :label="__('ui.remember_me')" />
                <a class="text-sm font-semibold text-indigo-600" href="{{ route('password.request') }}">{{ __('ui.forgot_password') }}</a>
            </div>
            <x-button type="submit" class="w-full">{{ __('ui.login') }}</x-button>
        </form>
    </x-card>
</x-layouts.guest>
