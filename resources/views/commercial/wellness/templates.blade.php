<x-layouts.app title="قالب‌های تمرین">
<section class="page-hero">
    <div>
        <p class="section-kicker">کتابخانه تمرین</p>
        <h1 class="page-title">قالب‌های برنامه</h1>
        <p class="page-subtitle">یک‌بار برنامه بنویسید (مثلاً «هوازی ماه اول»)، بعد آن را به چند شاگرد اختصاص دهید.</p>
    </div>
    <div class="flex gap-2">
        <x-button variant="secondary" :href="route('tenant.wellness.exercises.index')">کتابخانه حرکات</x-button>
    </div>
</section>

@if($errors->any())<x-alert tone="danger" class="mb-6">{{ $errors->first() }}</x-alert>@endif

<div class="grid gap-5 xl:grid-cols-[.75fr_1.25fr]">
    <div class="space-y-4">
        <x-card>
            <h2 class="font-black">قالب جدید</h2>
            <form method="POST" action="{{ route('tenant.wellness.templates.store') }}" class="mt-4 space-y-4">
                @csrf
                <x-input name="name" label="نام قالب" placeholder="مثلاً هوازی ماه اول" required/>
                <x-input name="goal" label="هدف"/>
                <x-textarea name="notes" label="توضیحات"/>

                <datalist id="exercise-list">@foreach($exercises as $exercise)<option value="{{ $exercise->name }}">@endforeach</datalist>
                @for($d = 0; $d < 3; $d++)
                <div class="rounded-xl border p-3">
                    <p class="text-sm font-bold">فاز / روز {{ $d + 1 }}</p>
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        <x-input name="days[{{ $d }}][phase]" label="فاز (مثلاً ماه اول)"/>
                        <x-input type="number" name="days[{{ $d }}][day_number]" label="روز" :value="$d + 1"/>
                        <x-input name="days[{{ $d }}][title]" label="عنوان"/>
                    </div>
                    @for($s = 0; $s < 2; $s++)
                    <div class="mt-2 grid grid-cols-5 gap-2">
                        <x-input name="days[{{ $d }}][sets][{{ $s }}][exercise_name]" label="حرکت" list="exercise-list" placeholder="نام یا انتخاب از لیست"/>
                        <x-input type="number" name="days[{{ $d }}][sets][{{ $s }}][set_number]" label="ست" :value="$s + 1"/>
                        <x-input type="number" step="0.25" name="days[{{ $d }}][sets][{{ $s }}][weight_kg]" label="کیلو"/>
                        <x-input type="number" name="days[{{ $d }}][sets][{{ $s }}][repetitions]" label="تکرار"/>
                        <x-input type="number" name="days[{{ $d }}][sets][{{ $s }}][duration_seconds]" label="مدت هوازی (ثانیه)"/>
                    </div>
                    @endfor
                    <p class="mt-1 text-xs muted">فیلدهای خالی روز/حرکت نادیده گرفته می‌شوند؛ برای حرکت‌های هوازی فقط «مدت هوازی» را پر کنید.</p>
                </div>
                @endfor

                <x-button type="submit">ذخیره قالب</x-button>
            </form>
        </x-card>
    </div>

    <div class="space-y-4">
        @forelse($templates as $template)
        <x-card>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <strong>{{ $template->name }}</strong>
                    <p class="mt-1 text-xs muted">{{ $template->coach ? $template->coach->full_name : 'قالب باشگاه' }} · {{ $template->days->count() }} روز · {{ $template->goal ?: '—' }}</p>
                </div>
                <form method="POST" action="{{ route('tenant.wellness.templates.destroy', $template) }}" onsubmit="return confirm('قالب حذف شود؟')">@csrf @method('DELETE')<x-button type="submit" variant="secondary">حذف</x-button></form>
            </div>
            @foreach($template->days as $day)
            <div class="mt-2 rounded-lg border p-2 text-sm">
                <b>{{ $day->phase ? $day->phase.' · ' : '' }}روز {{ $day->day_number }}{{ $day->title ? ' — '.$day->title : '' }}</b>
                @foreach($day->sets as $set)<p class="mt-0.5 text-xs muted">{{ $set->exercise_name }} — {{ $set->set_number }} ست{{ $set->weight_kg ? ' · '.$set->weight_kg.' کیلو' : '' }}{{ $set->repetitions ? ' · '.$set->repetitions.' تکرار' : '' }}{{ $set->duration_seconds ? ' · '.$set->duration_seconds.' ثانیه هوازی' : '' }}</p>@endforeach
            </div>
            @endforeach
            <form method="POST" action="{{ route('tenant.wellness.templates.assign', $template) }}" class="mt-3 border-t pt-3">
                @csrf
                <x-input type="date" name="starts_on" label="تاریخ شروع برنامه" :value="today()->toDateString()" required/>
                <div class="mt-2">
                    <p class="label">شاگردان</p>
                    <div class="mt-1 grid max-h-44 gap-1 overflow-auto sm:grid-cols-2">
                        @forelse($members as $member)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="member_ids[]" value="{{ $member->id }}">{{ $member->full_name }}</label>
                        @empty
                        <p class="text-xs muted">شاگردی برای اختصاص ندارید.</p>
                        @endforelse
                    </div>
                </div>
                <x-button type="submit" class="mt-3">اختصاص به شاگردان</x-button>
            </form>
        </x-card>
        @empty
        <x-empty-state title="قالبی ثبت نشده" description="از فرم «قالب جدید» شروع کنید تا بتوانید برنامه را به چند شاگرد اختصاص دهید."/>
        @endforelse
    </div>
</div>
</x-layouts.app>
