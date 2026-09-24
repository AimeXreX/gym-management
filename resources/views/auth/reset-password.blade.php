<x-layouts.guest :title="__('ui.reset_password')">
    <x-card>
        <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <x-input name="email" type="email" :label="__('ui.email')" :value="old('email', $request->email)" required />
            <x-input name="password" type="password" :label="__('ui.new_password')" required />
            <x-input name="password_confirmation" type="password" :label="__('ui.password_confirmation')" required />
            <x-button type="submit" class="w-full">{{ __('ui.reset_password') }}</x-button>
        </form>
    </x-card>
</x-layouts.guest>
