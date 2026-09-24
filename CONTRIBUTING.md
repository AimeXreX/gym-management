# مشارکت در توسعه

از مشارکت شما سپاسگزاریم. پیش از ارسال هر تغییر، لطفاً این راهنما، `AGENTS.md` و مستندات مرتبط در پوشه `docs/` را مطالعه کنید.

## قواعد الزامی

- نام کد، کلاس و دیتابیس انگلیسی؛ UI و متن‌ها فارسی و ترجمه‌پذیر.
- هر داده باشگاهی `gym_id` دارد و فقط از مسیر Scope مرکزی و fail-closed خوانده می‌شود.
- Entitlement ماژول، Role و Permission سه کنترل مستقل‌اند.
- Controller باریک؛ منطق در Action/Service و Query پیچیده در Query Object.
- وابستگی Core به ماژول تجاری ممنوع؛ API داخلی ماژول‌ها صریح باشد.
- Production نباید به Node، Docker، Redis، Supervisor یا root نیاز داشته باشد.
- قبل از تغییر معماری، ADR بسازید یا به ADR موجود ارجاع دهید.

## جریان کار

1. از `main` یک شاخه بگیرید: `git checkout -b feat/<نام>` یا `fix/<نام>`.
2. تغییرات را کوچک، deployable و دارای تست نگه دارید.
3. تست‌های Unit/Feature/Architecture مرتبط را بنویسید یا به‌روزرسانی کنید.
4. کد را با Laravel Pint مرتب کنید: `vendor/bin/pint`.
5. تست‌ها را اجرا کنید (بخش «تست» را ببینید).
6. یک Pull Request با چک‌لیست کامل بسازید.

## تست

- اجرای سریع روی SQLite درون‌حافظه: `php artisan test`
- تأیید MySQL 8 روی دیتابیس اختصاصی `_test`: `bash scripts/test-mysql.sh` (جزئیات در `docs/MYSQL-VERIFICATION.md`)
- برای هر read/write باشگاهی، تست cross-tenant (مثبت و منفی) و تست مسیر منفی authorization الزامی است.

## معیار Done مشترک

هر تغییر باید پیش از merge همه موارد زیر را پاس کند:

- acceptance criteria و تست Unit/Feature/Architecture سبز
- تست cross-tenant برای هر read/write باشگاهی
- authorization سمت سرور و تست مسیر منفی
- migration forward-safe و rollback/runbook متناسب
- ترجمه فارسی، RTL، keyboard و stateهای UI
- pagination، eager loading و query budget
- audit برای عملیات حساس
- مستندات و ADRهای متأثر به‌روز
- review و smoke test بدون regression شناخته‌شده بحرانی

## تغییر معماری

هر تغییری که به ساختار ماژول‌ها، tenancy، entitlement یا authorization مربوط است، ابتدا نیاز به ADR دارد. فایل‌های ADR در `docs/adr/` نگهداری می‌شوند؛ اگر تصمیم جدیدی گرفته شد، یک ADR جدید بسازید یا به ADR موجود ارجاع دهید.

## گزارش باگ و درخواست ویژگی

- برای گزارش باگ از قالب «گزارش باگ» استفاده کنید و مراحل بازتولید، رفتار مورد انتظار و محیط را کامل بنویسید.
- برای درخواست ویژگی از قالب «درخواست ویژگی» استفاده کنید و ماژول/محدوده تأثیر را مشخص کنید.
