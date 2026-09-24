<x-layouts.app title="کتابخانه حرکات">
<section class="page-hero">
    <div>
        <p class="section-kicker">کتابخانه تمرین</p>
        <h1 class="page-title">حرکات</h1>
        <p class="page-subtitle">حرکت‌های قابلاستفاده در قالب‌ها و برنامه‌ها؛ یک‌بار ثبت می‌شوند و همه مربیان باشگاه از آن‌ها استفاده می‌کنند.</p>
    </div>
    <div class="flex gap-2">
        <x-button variant="secondary" :href="route('tenant.wellness.templates.index')">قالب‌های برنامه</x-button>
    </div>
</section>

@if($errors->any())<x-alert tone="danger" class="mb-6">{{ $errors->first() }}</x-alert>@endif

<div class="grid gap-5 xl:grid-cols-[.45fr_1.55fr]">
    <x-card>
        <h2 class="font-black">حرکت جدید</h2>
        <form method="POST" action="{{ route('tenant.wellness.exercises.store') }}" class="mt-4 space-y-3">
            @csrf
            <x-input name="name" label="نام حرکت" required/>
            <x-select name="category" label="دسته"><option value="strength">قدرتی</option><option value="cardio">هوازی</option><option value="stretching">کششی</option></x-select>
            <x-input name="muscle_group" label="گروه عضلانی"/>
            <x-input name="equipment" label="تجهیزات"/>
            <x-textarea name="description" label="توضیح"/>
            <x-button type="submit">افزودن به کتابخانه</x-button>
        </form>
    </x-card>

    <x-card>
        <h2 class="font-black">حرکات ثبت‌شده</h2>
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($exercises as $exercise)
            <div class="flex items-start justify-between rounded-xl border p-3">
                <div>
                    <strong>{{ $exercise->name }}</strong>
                    <p class="mt-1 text-xs muted">{{ ['strength'=>'قدرتی','cardio'=>'هوازی','stretching'=>'کششی'][$exercise->category] ?? $exercise->category }}{{ $exercise->muscle_group ? ' · '.$exercise->muscle_group : '' }}{{ $exercise->equipment ? ' · '.$exercise->equipment : '' }}</p>
                </div>
                <form method="POST" action="{{ route('tenant.wellness.exercises.destroy', $exercise) }}" onsubmit="return confirm('حرکت حذف شود؟')">@csrf @method('DELETE')<x-button type="submit" variant="secondary">حذف</x-button></form>
            </div>
            @empty
            <div class="sm:col-span-2 lg:col-span-3"><x-empty-state title="حرکتی ثبت نشده" description="از فرم «حرکت جدید» شروع کنید."/></div>
            @endforelse
        </div>
    </x-card>
</div>
</x-layouts.app>
