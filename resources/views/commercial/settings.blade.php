<x-layouts.app title="تنظیمات باشگاه">
@php($s=array_merge(['expiry_warning_days'=>14,'membership_code_prefix'=>'GYM','duplicate_checkin_minutes'=>15,'qr_checkin_enabled'=>true,'expired_checkin_policy'=>'block'],$gym->settings_json??[]))
<div class="mb-7"><h1 class="page-title">تنظیمات عملیاتی باشگاه</h1><p class="page-subtitle">این گزینه‌ها مستقیماً روی عضویت، پذیرش، تاریخ و نمایش مالی اثر دارند.</p></div>
<x-card><form method="POST" enctype="multipart/form-data" action="{{ route('tenant.settings.update') }}" class="grid gap-4 md:grid-cols-2">@csrf @method('PUT')
<x-input name="timezone" label="منطقه زمانی" :value="$gym->timezone" required/><x-input name="currency" label="واحد پول" :value="$gym->currency" maxlength="3" required/>
<x-select name="default_branch_id" label="شعبه پیش‌فرض">@foreach($branches as $b)<option value="{{ $b->id }}" @selected($b->is_system_default)>{{ $b->name }}</option>@endforeach</x-select>
<x-input name="expiry_warning_days" type="number" label="هشدار انقضا (روز)" :value="$s['expiry_warning_days']" min="1" max="365" required/>
<x-input name="membership_code_prefix" label="پیشوند کد عضویت" :value="$s['membership_code_prefix']" required/><x-input name="duplicate_checkin_minutes" type="number" label="فاصله جلوگیری از ورود تکراری (دقیقه)" :value="$s['duplicate_checkin_minutes']" min="1" required/>
<x-select name="expired_checkin_policy" label="ورود عضویت منقضی"><option value="block" @selected($s['expired_checkin_policy']==='block')>مسدود</option><option value="allow" @selected($s['expired_checkin_policy']==='allow')>مجاز با حفظ سابقه</option></x-select>
<label class="flex items-center gap-2"><input type="checkbox" name="qr_checkin_enabled" value="1" @checked($s['qr_checkin_enabled'])> ورود با QR فعال باشد</label>
<label>لوگوی باشگاه<input class="field mt-2" type="file" name="logo" accept="image/jpeg,image/png,image/webp"></label><div class="md:col-span-2"><x-button type="submit">ذخیره تنظیمات</x-button></div>
</form></x-card></x-layouts.app>
