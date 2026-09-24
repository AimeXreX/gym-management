<x-layouts.app title="داشبورد مربی">
<section class="page-hero">
    <div>
        <p class="section-kicker">پرتال مربی</p>
        <h1 class="page-title">داشبورد</h1>
        <p class="page-subtitle">{{ $coach->full_name }} · {{ $coach->coach_type_label }}</p>
    </div>
</section>

<section class="premium-card mb-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-black">دسترسی‌های من</h2>
            <p class="mt-1 text-xs muted">همه ابزارهای مجاز شما بر اساس نوع مربی و انتساب‌های فعال.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn btn-secondary" href="{{ route('tenant.wellness.index') }}">ورزشکاران من</a>
            <a class="btn btn-secondary" href="{{ route('tenant.wellness.coach-requests.index') }}">درخواست‌ها</a>
            @if($canTraining)<a class="btn btn-secondary" href="{{ route('tenant.wellness.templates.index') }}">قالب‌های تمرینی</a>@endif
            @if($canTraining)<a class="btn btn-secondary" href="{{ route('tenant.wellness.exercises.index') }}">کتابخانه حرکات</a>@endif
            <a class="btn btn-secondary" href="{{ route('tenant.wellness.profile') }}">پروفایل من</a>
        </div>
    </div>
</section>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <x-stat-card label="شاگردان" :value="(string) $roster->count()" icon="users"/>
    <x-stat-card label="درخواست‌های باز" :value="(string) $pendingRequests->count()" icon="inbox"/>
    <x-stat-card label="کلاس‌های امروز" :value="(string) $todayClasses->count()" icon="calendar"/>
    @if($canTraining)<x-stat-card label="برنامه تمرینی فعال" :value="(string) $activeProgramsCount" icon="dumbbell"/>@endif
    @if($canNutrition)<x-stat-card label="برنامه غذایی جاری" :value="(string) $activeNutritionPlansCount" icon="activity"/>@endif
</div>

<div class="mt-4 flex flex-wrap gap-2">
    @if($canTraining)<x-badge tone="brand">مربی تمرین · {{ $trainingMembersCount }} شاگرد · {{ $templatesCount }} قالب</x-badge>@endif
    @if($canNutrition)<x-badge tone="success">مربی تغذیه · {{ $nutritionMembersCount }} شاگرد</x-badge>@endif
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-2">
    <section class="premium-card">
        <h2 class="font-black">کلاس‌های امروز</h2>
        @forelse($todayClasses as $session)
            <div class="mt-3 rounded-xl border p-3">
                <div class="flex items-center justify-between">
                    <strong>{{ $session->gymClass->name }}</strong>
                    <x-badge tone="success">{{ $session->starts_at->format('H:i') }} تا {{ $session->ends_at->format('H:i') }}</x-badge>
                </div>
                <p class="mt-1 text-xs muted">{{ $session->gymClass->branch?->name ?? '—' }} · {{ $session->enrollments_count }} ثبت‌نام</p>
            </div>
        @empty
            <x-empty-state title="کلاسی برای امروز ندارید"/>
        @endforelse
    </section>

    <section class="premium-card">
        <div class="flex items-center justify-between">
            <h2 class="font-black">درخواست‌های در انتظار</h2>
            <a class="text-xs font-semibold" style="color:var(--brand)" href="{{ route('tenant.wellness.coach-requests.index') }}">همه</a>
        </div>
        @forelse($pendingRequests as $req)
            <div class="mt-3 rounded-xl border p-3">
                <strong>{{ $req->member->full_name }} · {{ $req->type === 'assign' ? 'درخواست مربی' : 'درخواست برنامه' }} · {{ $req->domain === 'training' ? 'تمرین' : 'تغذیه' }}</strong>
                @if($req->message)<p class="mt-1 text-xs muted">{{ $req->message }}</p>@endif
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('tenant.wellness.coach-requests.review', $req) }}">@csrf<input type="hidden" name="approve" value="1"><x-button type="submit">تأیید</x-button></form>
                    <form method="POST" action="{{ route('tenant.wellness.coach-requests.review', $req) }}" class="flex items-center gap-2">@csrf<input type="hidden" name="approve" value="0"><input class="field" name="review_note" placeholder="دلیل رد" required><x-button type="submit" variant="secondary">رد</x-button></form>
                </div>
            </div>
        @empty
            <x-empty-state title="درخواست بازی ندارید"/>
        @endforelse
    </section>
</div>

<section class="premium-card mt-5">
    <h2 class="font-black">کلاس‌های پیش رو (۷ روز آینده)</h2>
    <div class="mt-4 space-y-2">
        @forelse($upcomingClasses as $session)
            <div class="flex items-center justify-between rounded-xl border p-3">
                <div>
                    <strong>{{ $session->gymClass->name }}</strong>
                    <p class="mt-1 text-xs muted">{{ $session->starts_at->format('Y-m-d H:i') }} · {{ $session->gymClass->branch?->name ?? '—' }} · {{ $session->enrollments_count }} ثبت‌نام</p>
                </div>
                <x-badge tone="brand">{{ $session->starts_at->format('H:i') }}</x-badge>
            </div>
        @empty
            <x-empty-state title="کلاس آینده‌ای ندارید"/>
        @endforelse
    </div>
</section>

<section class="premium-card mt-5">
    <h2 class="font-black">شاگردان من</h2>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($roster as $member)
            @php($memberDomains = $domains->get($member->id) ?? collect())
            <a href="{{ route('tenant.wellness.index', $member) }}" class="rounded-xl border p-4 transition hover:border-[var(--brand)]">
                <div class="flex items-start justify-between gap-2">
                    <strong>{{ $member->full_name }}</strong>
                    <span class="flex gap-1">@foreach($memberDomains as $d)<x-badge :tone="$d === 'training' ? 'brand' : 'success'">{{ $d === 'training' ? 'تمرین' : 'تغذیه' }}</x-badge>@endforeach</span>
                </div>
                <p class="mt-2 text-xs muted">آخرین وزن: {{ $member->bodyMeasurements->first()?->weight_kg ?: '—' }} کیلو</p>
                <p class="mt-1 text-xs muted">برنامه فعال: {{ $member->workoutPrograms->first()?->title ?? '—' }}</p>
            </a>
        @empty
            <div class="sm:col-span-2 lg:col-span-3"><x-empty-state title="شاگردی ندارید" description="وقتی مدیر عضوی را به شما منتسب کند، اینجا دیده می‌شود."/></div>
        @endforelse
    </div>
</section>
</x-layouts.app>
