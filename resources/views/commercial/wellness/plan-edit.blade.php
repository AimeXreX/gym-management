<x-layouts.app title="ویرایش برنامه غذایی">
<section class="page-hero">
    <div>
        <p class="section-kicker">برنامه غذایی</p>
        <h1 class="page-title">ویرایش برنامه</h1>
        <p class="page-subtitle">{{ $plan->title }} · {{ $member->full_name }}</p>
    </div>
</section>

@if($errors->any())<x-alert tone="danger" class="mb-6">{{ $errors->first() }}</x-alert>@endif

<form method="POST" action="{{ route('tenant.wellness.plans.update', $plan) }}" class="space-y-4">
    @csrf @method('PUT')
    <x-card>
        <h2 class="font-black">مشخصات برنامه</h2>
        <div class="mt-3"><x-input name="title" label="عنوان" :value="old('title', $plan->title)" required/></div>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <x-input type="date" name="starts_on" label="شروع" :value="old('starts_on', $plan->starts_on->format('Y-m-d'))" required/>
            <x-input type="date" name="ends_on" label="پایان" :value="old('ends_on', $plan->ends_on?->format('Y-m-d'))"/>
        </div>
        <div class="mt-3"><x-input name="goal" label="هدف" :value="old('goal', $plan->goal)"/></div>
        <div class="mt-3"><x-textarea name="notes" label="توضیحات مربی" :value="old('notes', $plan->notes)"/></div>
    </x-card>

    @foreach($plan->items as $itemIndex => $item)
    <x-card>
        <h2 class="font-black">وعده {{ $itemIndex + 1 }}</h2>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <x-input name="items[{{ $itemIndex }}][meal_name]" label="نام وعده" :value="$item->meal_name" required/>
            <x-input type="time" name="items[{{ $itemIndex }}][suggested_at]" label="ساعت پیشنهادی" :value="$item->suggested_at"/>
        </div>
        <div class="mt-3"><x-textarea name="items[{{ $itemIndex }}][foods]" label="مواد غذایی و مقدار" :value="$item->foods" required/></div>
    </x-card>
    @endforeach

    <x-card>
        <h2 class="font-black">افزودن وعده جدید</h2>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <x-input name="items[new][meal_name]" label="نام وعده"/>
            <x-input type="time" name="items[new][suggested_at]" label="ساعت پیشنهادی"/>
        </div>
        <div class="mt-3"><x-textarea name="items[new][foods]" label="مواد غذایی و مقدار"/></div>
    </x-card>

    <div class="flex gap-2">
        <x-button type="submit">ذخیره تغییرات</x-button>
        <x-button variant="secondary" :href="route('tenant.wellness.index', $member)">انصراف</x-button>
    </div>
</form>
</x-layouts.app>
