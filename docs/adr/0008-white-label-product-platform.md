# ADR 0008 — پلتفرم White-label و مجوزهای قابل توسعه

## وضعیت

پذیرفته‌شده — 2026-08-01

## زمینه

محصول باید از یک codebase به باشگاه‌های متعدد فروخته شود، بدون fork مشتری و بدون افشای سورس. هر Gym به قابلیت‌ها، برند و تجربه بصری مستقل نیاز دارد و اپلیکیشن‌های آینده باید از همان مرزهای دسترسی استفاده کنند.

## تصمیم

- قابلیت‌ها با entitlement ماژول فعال می‌شوند؛ entitlement هرگز جای permission را نمی‌گیرد.
- authorization باشگاهی از Permission Service مرکزی و fail-closed عبور می‌کند. owner تمام permissionهای باشگاه را دارد؛ نقش‌های دیگر فقط permissionهای صریح یا catalog پیش‌فرض نقش خود را دارند.
- UI هر Gym از Brand Profile داده‌محور ساخته می‌شود: preset، رنگ اصلی/تأکیدی، radius، density، لوگو و tagline. CSS tokenها در request تولید می‌شوند و هیچ view مشتری fork نمی‌شود.
- presetها (`midnight`, `aurora`, `graphite`) تنها نقطه شروع‌اند و تمام قابلیت‌های امنیتی مستقل از theme باقی می‌مانند.
- API آینده همان Gym Context، entitlement و permission را استفاده می‌کند؛ منطق تجاری به UI وابسته نمی‌شود.

## پیامدها

افزودن مشتری جدید migration یا deploy اختصاصی نمی‌خواهد. تغییرات bespoke ابتدا به config/extension point تبدیل می‌شوند؛ fork مشتری ممنوع است. هر permission و theme setting جدید تست tenant isolation و fallback دارد.

