# ADR 0005: سیاست Module Entitlement

**وضعیت:** پذیرفته‌شده

## تصمیم

Registry منبع حقیقت key و dependency است. key ناشناخته، entitlement غیرفعال یا dependency غیرفعال نتیجه `false` دارد. Route middleware، navigation، Blade directive و service از همان `ModuleManager` استفاده می‌کنند. cache فقط درون request و tenant-keyed است.

## پیامد

مخفی‌سازی UI جایگزین کنترل سرور نیست و تعویض Gym نتیجه entitlement قبلی را reuse نمی‌کند.
