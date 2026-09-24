# ADR 0003: Scope مدل‌های Tenant-owned

**وضعیت:** پذیرفته‌شده

## تصمیم

مدل tenant-owned صریحاً trait `BelongsToGym` دارد. trait، `GymScope` را اعمال، `gym_id` را هنگام create از Context جایگزین و تغییر مالکیت پس از create را رد می‌کند. Resolver پیش از implicit binding در middleware priority اجرا می‌شود.

## پیامد

query، create، update و route binding یک مرز مرکزی دارند. `TenantProbe` داخلی این قرارداد را بدون افزودن Feature تجاری تست می‌کند.
