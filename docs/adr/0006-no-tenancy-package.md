# ADR 0006: عدم استفاده از Tenancy Package در Foundation

**وضعیت:** پذیرفته‌شده

## تصمیم

برای Shared Database/Shared Schema از سرویس‌های کوچک داخلی Laravel شامل Context، resolver، global scope و middleware استفاده می‌شود.

## دلیل

نیاز فعلی محدود و روشن است؛ package خارجی پیچیدگی lifecycle، upgrade و shared-host deployment را بدون ارزش متناسب افزایش می‌دهد. در صورت تغییر به database-per-tenant این ADR بازبینی می‌شود.
