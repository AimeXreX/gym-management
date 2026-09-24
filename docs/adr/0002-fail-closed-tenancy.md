# ADR 0002: سیاست Fail-closed Tenancy

**وضعیت:** پذیرفته‌شده

## تصمیم

نبود Context معتبر هر query مدل tenant-owned را با exception متوقف می‌کند. انتخاب stale، خارجی یا غیرفعال هرگز Context نمی‌سازد. Platform Admin bypass در Foundation وجود ندارد.

## پیامد

خطای wiring به نشت داده تبدیل نمی‌شود. command و job آینده باید Context را صریح resolve کنند.
