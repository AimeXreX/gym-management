<x-layouts.app :title="$coach->full_name">
<section class="page-hero">
    <div class="flex flex-wrap items-center gap-4">
        @if($coach->avatar_path)
            <img class="size-16 rounded-2xl object-cover" src="{{ route('tenant.coaches.avatar', $coach) }}" alt="">
        @else
            <span class="grid size-16 place-items-center rounded-2xl bg-indigo-50 text-xl font-black">{{ mb_substr($coach->first_name,0,1).mb_substr($coach->last_name,0,1) }}</span>
        @endif
        <div>
            <p class="section-kicker">پروفایل من</p>
            <h1 class="page-title">{{ $coach->full_name }}</h1>
            <p class="page-subtitle">{{ $coach->coach_type_label }} · {{ $coach->branch?->name ?? 'همه شعب' }}</p>
        </div>
    </div>
</section>

<div class="mt-5 grid gap-5 lg:grid-cols-2">
    <x-card>
        <h2 class="font-black">ویرایش اطلاعات</h2>
        <form method="POST" enctype="multipart/form-data" action="{{ route('tenant.wellness.profile.update') }}" class="mt-4 space-y-3">
            @csrf @method('PUT')
            <x-input name="mobile" label="موبایل" :value="old('mobile', $coach->mobile)" required/>
            <x-input name="email" type="email" label="ایمیل" :value="old('email', $coach->email)"/>
            <x-input name="specialty" label="تخصص" :value="old('specialty', $coach->specialty)"/>
            <x-textarea name="biography" label="بیوگرافی" :value="old('biography', $coach->biography)"/>
            <label class="label">تصویر پروفایل</label>
            <input class="field" type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
            <x-button type="submit">ذخیره</x-button>
        </form>
    </x-card>

    <x-card>
        <h2 class="font-black">اطلاعات فعلی</h2>
        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between gap-4"><dt class="muted">نوع مربی</dt><dd class="font-bold">{{ $coach->coach_type_label }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="muted">شعبه</dt><dd class="font-bold">{{ $coach->branch?->name ?? 'همه شعب' }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="muted">تاریخ استخدام</dt><dd class="font-bold">{{ $coach->hire_date?->format('Y-m-d') ?? '—' }}</dd></div>
            @if($coach->user)
                <div class="flex justify-between gap-4"><dt class="muted">حساب ورود</dt><dd class="font-bold">{{ $coach->user->email }}</dd></div>
            @endif
        </dl>
        <p class="mt-4 text-xs muted">نوع مربی، شعبه و یادداشت‌های مالی فقط توسط مدیر باشگاه قابل تغییر است.</p>
    </x-card>
</div>
</x-layouts.app>
