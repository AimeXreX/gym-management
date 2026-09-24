<x-layouts.app title="ویرایش برنامه تمرینی">
<section class="page-hero">
    <div>
        <p class="section-kicker">برنامه تمرینی</p>
        <h1 class="page-title">ویرایش برنامه</h1>
        <p class="page-subtitle">{{ $program->title }} · {{ $member->full_name }}</p>
    </div>
</section>

@if($errors->any())<x-alert tone="danger" class="mb-6">{{ $errors->first() }}</x-alert>@endif

<form method="POST" action="{{ route('tenant.wellness.programs.update', $program) }}" class="space-y-4">
    @csrf @method('PUT')
    <x-card>
        <h2 class="font-black">مشخصات برنامه</h2>
        <div class="mt-3"><x-input name="title" label="عنوان برنامه" :value="old('title', $program->title)" required/></div>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <x-input type="date" name="starts_on" label="شروع" :value="old('starts_on', $program->starts_on->format('Y-m-d'))" required/>
            <x-input type="date" name="ends_on" label="پایان" :value="old('ends_on', $program->ends_on?->format('Y-m-d'))"/>
        </div>
        <div class="mt-3"><x-input name="goal" label="هدف" :value="old('goal', $program->goal)"/></div>
        <div class="mt-3"><x-textarea name="notes" label="یادداشت برنامه" :value="old('notes', $program->notes)"/></div>
    </x-card>

    @foreach($program->days as $dayIndex => $day)
    <x-card>
        <h2 class="font-black">روز {{ $day->day_number }}{{ $day->title ? ' — '.$day->title : '' }}</h2>
        <div class="mt-3 grid grid-cols-3 gap-2">
            <x-input type="number" name="days[{{ $dayIndex }}][day_number]" label="شماره روز" :value="$day->day_number" required/>
            <x-input name="days[{{ $dayIndex }}][title]" label="عنوان روز" :value="$day->title"/>
        </div>
        @foreach($day->sets as $setIndex => $set)
        <div class="mt-3 grid grid-cols-4 gap-2">
            <x-input name="days[{{ $dayIndex }}][sets][{{ $setIndex }}][exercise_name]" label="حرکت" :value="$set->exercise_name" required/>
            <x-input type="number" name="days[{{ $dayIndex }}][sets][{{ $setIndex }}][set_number]" label="ست" :value="$set->set_number" required/>
            <x-input type="number" step="0.25" name="days[{{ $dayIndex }}][sets][{{ $setIndex }}][weight_kg]" label="کیلو" :value="$set->weight_kg"/>
            <x-input type="number" name="days[{{ $dayIndex }}][sets][{{ $setIndex }}][repetitions]" label="تعداد" :value="$set->repetitions"/>
        </div>
        @endforeach
        <div class="mt-3 grid grid-cols-4 gap-2">
            <x-input name="days[{{ $dayIndex }}][sets][new][exercise_name]" label="حرکت جدید"/>
            <x-input type="number" name="days[{{ $dayIndex }}][sets][new][set_number]" label="ست"/>
            <x-input type="number" step="0.25" name="days[{{ $dayIndex }}][sets][new][weight_kg]" label="کیلو"/>
            <x-input type="number" name="days[{{ $dayIndex }}][sets][new][repetitions]" label="تعداد"/>
        </div>
    </x-card>
    @endforeach

    <x-card>
        <h2 class="font-black">افزودن روز جدید</h2>
        <div class="mt-3 grid grid-cols-3 gap-2">
            <x-input type="number" name="days[new][day_number]" label="شماره روز"/>
            <x-input name="days[new][title]" label="عنوان روز"/>
        </div>
        <div class="mt-3 grid grid-cols-4 gap-2">
            <x-input name="days[new][sets][0][exercise_name]" label="حرکت"/>
            <x-input type="number" name="days[new][sets][0][set_number]" label="ست"/>
            <x-input type="number" step="0.25" name="days[new][sets][0][weight_kg]" label="کیلو"/>
            <x-input type="number" name="days[new][sets][0][repetitions]" label="تعداد"/>
        </div>
    </x-card>

    <div class="flex gap-2">
        <x-button type="submit">ذخیره تغییرات</x-button>
        <x-button variant="secondary" :href="route('tenant.wellness.index', $member)">انصراف</x-button>
    </div>
</form>
</x-layouts.app>
