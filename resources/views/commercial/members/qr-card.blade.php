<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width">
    <title>کارت {{ $member->full_name }}</title>
    @vite(['resources/js/app.js'])
    <style>body{font-family:Tahoma;background:#f3f4f6;padding:32px}.card{width:340px;max-width:calc(100vw - 64px);margin:auto;background:#fff;border:1px solid #ddd;border-radius:20px;padding:28px;text-align:center}.token{direction:ltr;word-break:break-all;font:11px monospace;background:#f5f5f5;padding:10px;border-radius:10px}.code{font-size:24px;font-weight:900}.actions{display:flex;gap:8px;justify-content:center}canvas{max-width:100%;height:auto}@media print{body{background:#fff;padding:0}.actions{display:none}.card{border:0}}</style>
</head>
<body><div class="card">
    <h2>{{ app(\App\Core\Tenancy\GymContext::class)->gym()->name }}</h2>
    <p class="code">{{ $member->membership_code }}</p><h1>{{ $member->full_name }}</h1>
    <canvas data-qr-value="{{ $member->public_token }}" aria-label="QR ورود عضو"></canvas>
    <p>انقضا: {{ $member->memberships->first()?->ends_at?->format('Y-m-d') ?? '—' }}</p>
    <p class="token">{{ $member->public_token }}</p><p><small>QR فقط توکن تصادفی امن ورود را نگهداری می‌کند.</small></p>
    <div class="actions"><button type="button" onclick="print()">چاپ کارت</button><form method="POST" action="{{ route('tenant.members.qr-token',$member) }}" onsubmit="return confirm('توکن قبلی بلافاصله نامعتبر می‌شود. ادامه می‌دهید؟')">@csrf<button>تغییر توکن</button></form></div>
</div></body></html>
