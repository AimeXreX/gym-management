<x-layouts.app title="اعلان‌ها">
<section class="page-hero">
    <div>
        <p class="section-kicker">صندوق پیام</p>
        <h1 class="page-title">اعلان‌ها</h1>
        <p class="page-subtitle">درخواست‌ها، تأییدها و برنامه‌های جدیدی که به شما مربوط است.</p>
    </div>
    @if($unreadCount > 0)
        <form method="POST" action="{{ route('tenant.notifications.read-all') }}">@csrf<x-button variant="secondary">علامت‌گذاری همه به‌عنوان خوانده‌شده</x-button></form>
    @endif
</section>

@forelse($notifications as $notification)
    <a href="{{ route('tenant.notifications.read', $notification) }}" class="premium-card mb-3 flex items-start gap-3 p-4 {{ $notification->read_at ? 'opacity-60' : '' }}">
        <x-icon name="bell" class="mt-1 shrink-0" style="{{ $notification->read_at ? '' : 'color:var(--brand)' }}"/>
        <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
                <strong class="truncate text-sm">{{ $notification->title }}</strong>
                <x-badge :tone="$notification->read_at ? 'neutral' : 'warning'">{{ $notification->read_at ? 'خوانده‌شده' : 'جدید' }}</x-badge>
            </div>
            @if($notification->message)<p class="mt-1 text-xs leading-6 muted">{{ $notification->message }}</p>@endif
            <small class="mt-1 block text-[11px] muted">{{ $notification->created_at->format('Y-m-d H:i') }}</small>
        </div>
    </a>
@empty
    <x-empty-state title="اعلانی ندارید" description="وقتی مربی یا مدیر اقدامی انجام دهد، اینجا مطلع می‌شوید."/>
@endforelse

@if($notifications->hasPages())
    <div class="mt-4">{{ $notifications->links() }}</div>
@endif
</x-layouts.app>
