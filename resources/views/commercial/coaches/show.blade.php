<x-layouts.app :title="$coach->full_name">
    <div class="mb-7 flex flex-wrap items-center gap-4">
        @if($coach->avatar_path)
            <img class="size-16 rounded-2xl object-cover" src="{{ route('tenant.coaches.avatar', $coach) }}" alt="">
        @else
            <span class="grid size-16 place-items-center rounded-2xl bg-indigo-50 text-xl font-black">{{ mb_substr($coach->first_name,0,1).mb_substr($coach->last_name,0,1) }}</span>
        @endif
        <div>
            <p class="section-kicker">{{ $coach->coach_type_label }}</p>
            <h1 class="page-title">{{ $coach->full_name }}</h1>
            <p class="page-subtitle">{{ $coach->specialty ?: '—' }} · {{ $coach->branch?->name ?? 'همه شعب' }} · {{ $coach->mobile }}</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card label="وضعیت" :value="$coach->status === 'active' ? 'فعال' : 'آرشیو'" :tone="$coach->status === 'active' ? 'emerald' : 'slate'"/>
        <x-stat-card label="تاریخ استخدام" :value="$coach->hire_date?->format('Y-m-d') ?? '—'" icon="calendar"/>
        <x-stat-card label="اعضای منتسب" :value="(string) $coach->memberAssignments->count()" icon="users"/>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <x-card>
            <h2 class="font-black">اطلاعات تماس</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="muted">موبایل</dt><dd class="font-bold">{{ $coach->mobile }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="muted">ایمیل</dt><dd class="font-bold">{{ $coach->email ?: '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="muted">شعبه</dt><dd class="font-bold">{{ $coach->branch?->name ?? 'همه شعب' }}</dd></div>
                @if($coach->user)
                    <div class="flex justify-between gap-4"><dt class="muted">حساب ورود</dt><dd class="font-bold">{{ $coach->user->email }}</dd></div>
                @endif
            </dl>
        </x-card>

        @if($coach->biography)
            <x-card>
                <h2 class="font-black">بیوگرافی</h2>
                <p class="mt-3 whitespace-pre-line text-sm leading-7 muted">{{ $coach->biography }}</p>
            </x-card>
        @endif

        @if($isManager && $coach->compensation_notes)
            <x-card>
                <h2 class="font-black">یادداشت‌های مالی (دستمزد)</h2>
                <p class="mt-3 whitespace-pre-line text-sm leading-7 muted">{{ $coach->compensation_notes }}</p>
            </x-card>
        @endif

        <x-card>
            <h2 class="font-black">اعضای منتسب</h2>
            <div class="mt-3 space-y-2">
                @forelse($coach->memberAssignments as $assignment)
                    <div class="flex items-center justify-between rounded-xl border p-3">
                        <a class="font-bold text-indigo-600" href="{{ route('tenant.wellness.index', $assignment->member) }}">{{ $assignment->member->full_name }}</a>
                        <x-badge :tone="$assignment->domain === 'training' ? 'brand' : 'success'">{{ $assignment->domain === 'training' ? 'تمرین' : 'تغذیه' }}</x-badge>
                    </div>
                @empty
                    <x-empty-state title="عضوی منتسب نشده" description="اعضایی که در دامنه تمرین یا تغذیه به این مربی متصل شده‌اند اینجا نمایش داده می‌شوند."/>
                @endforelse
            </div>
        </x-card>

        <x-card>
            <h2 class="font-black">کلاس‌های پیش رو</h2>
            <div class="mt-4 space-y-2">
                @forelse($coach->classes as $class)
                    @foreach($class->sessions as $session)
                        <p>{{ $class->name }} — {{ $session->starts_at->format('Y-m-d H:i') }}</p>
                    @endforeach
                @empty
                    <x-empty-state title="کلاس آینده‌ای وجود ندارد" description="پس از تولید جلسات، برنامه مربی اینجا نمایش داده می‌شود."/>
                @endforelse
            </div>
        </x-card>
    </div>
</x-layouts.app>
