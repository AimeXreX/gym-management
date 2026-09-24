<div class="grid gap-5 md:grid-cols-2">
    <x-input name="name" :label="__('ui.gym_name')" :value="old('name', $gym->name ?? '')" required />
    <x-input name="slug" :label="__('ui.slug')" :value="old('slug', $gym->slug ?? '')" required dir="ltr" />
    <x-select name="status" :label="__('ui.status')" required>
        <option value="active" @selected(old('status', $gym->status ?? 'active') === 'active')>{{ __('ui.active') }}</option>
        <option value="inactive" @selected(old('status', $gym->status ?? '') === 'inactive')>{{ __('ui.inactive') }}</option>
    </x-select>
    <x-select name="timezone" :label="__('ui.timezone')" required>
        @foreach(['Asia/Tehran', 'UTC', 'Asia/Dubai'] as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', $gym->timezone ?? 'Asia/Tehran') === $timezone)>{{ $timezone }}</option>@endforeach
    </x-select>
    <x-select name="locale" :label="__('ui.locale')" required>
        <option value="fa" @selected(old('locale', $gym->locale ?? 'fa') === 'fa')>فارسی</option>
        <option value="en" @selected(old('locale', $gym->locale ?? '') === 'en')>English</option>
    </x-select>
</div>
