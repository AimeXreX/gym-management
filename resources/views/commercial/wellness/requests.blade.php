<x-layouts.app title="درخواست‌ها">
<section class="page-hero">
    <div>
        <p class="section-kicker">صندوق مربی</p>
        <h1 class="page-title">درخواست‌های مربی</h1>
        <p class="page-subtitle">درخواست‌های اعضا که خطاب به شما یا مربی پیش‌فرض دامنه ثبت شده است.</p>
    </div>
</section>

<section class="premium-card mb-6">
    <h2 class="font-black">در انتظار بررسی</h2>
    @forelse($pendingRequests as $req)
        <div class="mt-3 rounded-xl border p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <strong>{{ $req->member->full_name }} · {{ $req->type === 'assign' ? 'درخواست مربی' : 'درخواست برنامه' }} · {{ $req->domain === 'training' ? 'تمرین' : 'تغذیه' }}</strong>
                <x-badge tone="warning">در انتظار</x-badge>
            </div>
            @if($req->message)<p class="mt-2 text-sm muted">{{ $req->message }}</p>@endif
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('tenant.wellness.coach-requests.review', $req) }}">@csrf<input type="hidden" name="approve" value="1"><x-button type="submit">تأیید</x-button></form>
                <form method="POST" action="{{ route('tenant.wellness.coach-requests.review', $req) }}" class="flex items-center gap-2">@csrf<input type="hidden" name="approve" value="0"><input class="field" name="review_note" placeholder="دلیل رد" required><x-button type="submit" variant="secondary">رد</x-button></form>
            </div>
        </div>
    @empty
        <x-empty-state title="درخواست بازی ندارید" description="وقتی عضوی از شما یا مربی پیش‌فرض دامنه درخواست بدهد، اینجا می‌بینید."/>
    @endforelse
</section>

@if($history->isNotEmpty())
<section class="premium-card">
    <h2 class="font-black">بررسی‌های اخیر شما</h2>
    @foreach($history as $req)
        <div class="mt-3 rounded-xl border p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <strong>{{ $req->member->full_name }} · {{ $req->type === 'assign' ? 'درخواست مربی' : 'درخواست برنامه' }} · {{ $req->domain === 'training' ? 'تمرین' : 'تغذیه' }}</strong>
                <x-badge :tone="$req->status === 'approved' ? 'success' : 'neutral'">{{ $req->status === 'approved' ? 'تأییدشده' : 'ردشده' }}</x-badge>
            </div>
            @if($req->review_note)<p class="mt-1 text-xs muted">دلیل رد: {{ $req->review_note }}</p>@endif
        </div>
    @endforeach
</section>
@endif
</x-layouts.app>
