# توسعه و استقرار

## ماتریس محیط

| محیط | نیازمندی |
|---|---|
| توسعه/CI | PHP، Composer، Node برای Vite، MySQL یا DB تست |
| Production | PHP 8.3+ برای Laravel 13، Composer یا vendor آماده، MySQL، Cron |

Node، Docker، Redis، Supervisor و root در Production الزامی نیستند. پیش از scaffold، نسخه PHP و extensionهای میزبان با الزامات رسمی Laravel تطبیق داده می‌شود. اگر PHP 8.3 موجود نباشد، fallback موقت Laravel 12/PHP 8.2 باید در ADR ثبت شود.

## Build

Artifact در CI یا محیط توسعه ساخته می‌شود:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan test
```

Artifact شامل کد، `vendor` در صورت نبود Composer روی host، و `public/build` است؛ شامل `.env`، test output، source map عمومی ناخواسته یا `node_modules` نیست.

## چیدمان cPanel

ترجیح: document root دامنه روی `<release>/public` تنظیم شود. اگر cPanel اجازه ندهد، public assets و front controller با روش مستند و بدون افشای root برنامه قرار می‌گیرند؛ کپی کل برنامه داخل `public_html` ممنوع است.

دایرکتوری‌های `storage` و `bootstrap/cache` برای کاربر وب writable و سایر فایل‌ها read-only تا حد امکان‌اند. secretها فقط در `.env` خارج از web root و با دسترسی محدود ذخیره می‌شوند.

## روند release

1. preflight: PHP/extensions، فضای دیسک، DB و backup
2. upload به دایرکتوری release جدید
3. قراردادن `.env` و اتصال storage پایدار
4. `php artisan down --render=...` فقط اگر migration ناسازگار است
5. `php artisan migrate --force`
6. `php artisan optimize`
7. تغییر document root/symlink در صورت پشتیبانی؛ در غیر این صورت deploy زمان‌بندی‌شده
8. `php artisan up` و smoke test
9. نگهداری حداقل release قبلی برای rollback کد

rollback کد migration destructive را برنمی‌گرداند؛ schema با expand/contract سازگار نگه داشته می‌شود.

## Cron

هر دقیقه:

```cron
* * * * cd /path/to/app && php artisan schedule:run >/dev/null 2>&1
* * * * cd /path/to/app && php artisan queue:work database --stop-when-empty --tries=3 --max-time=50 >/dev/null 2>&1
```

مسیر PHP CLI در cPanel ممکن است متفاوت باشد. برای جلوگیری از overlap از scheduler locks استفاده می‌شود. job طولانی به chunkهای idempotent تقسیم می‌شود.

## تنظیمات Production

```text
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=database
CACHE_STORE=file|database
SESSION_DRIVER=database|file
LOG_CHANNEL=daily
```

`APP_KEY` هرگز بین releaseها تغییر نمی‌کند. HTTPS اجباری، cookie امن، backup رمزگذاری‌شده و rotation log تنظیم می‌شود. پس از deploy، config/route/view/event cache ساخته می‌شود.

### نکات Foundation

- خروجی `public/build` باید همراه release بارگذاری شود؛ اجرای `npm` روی cPanel لازم نیست.
- `SESSION_DRIVER=file` و `CACHE_STORE=file` تنظیم امن پایه‌اند؛ در صورت انتخاب Database برای هرکدام، migration متناظر باید جداگانه و آگاهانه اضافه شود.
- Seeder فعلی فقط در محیط `local` داده آزمایشی می‌سازد و نباید با تغییر جعلی `APP_ENV` در production اجرا شود.
- document root باید به `public` اشاره کند و `storage/framework` و `bootstrap/cache` writable باشند.

## اجرای Test Suite روی MySQL 8

در محیطی که MySQL 8 واقعاً نصب است:

1. یک دیتابیس خالی `gym_saas_testing` و کاربر محدود مخصوص تست بسازید.
2. `.env.testing.mysql.example` را به `.env.testing` کپی و فقط credential محلی را وارد کنید.
3. یک `APP_KEY` موقت با `php artisan key:generate --env=testing` بسازید.
4. اجرا کنید:

```bash
php artisan config:clear
php artisan migrate:fresh --env=testing --force
php artisan test
```

این دستور `migrate:fresh` مخرب است و فقط باید روی دیتابیس اختصاصی test اجرا شود. در محیط فعلی فقط SQLite اجرا شده و سازگاری MySQL از روی schema/query بازبینی و برای اجرای واقعی آماده شده است.

## Backup و بازیابی

- backup خودکار DB و فایل‌های upload با retention مشخص
- حداقل یک نسخه خارج از همان account میزبانی
- restore drill دوره‌ای؛ وجود backup بدون آزمون restore کافی نیست
- قبل از migration پرریسک snapshot مستقل

### کنترل نهایی و مانیتورینگ

- پیش از سوییچ دامنه، `php artisan app:production-check` باید بدون FAIL تمام شود.
- `/health` اتصال دیتابیس، writable بودن storage، heartbeat زمان‌بند و تازگی backup را بدون افشای credential گزارش می‌کند؛ مانیتور بیرونی هر ۵ دقیقه آن را بررسی کند.
- Cron فقط `schedule:run` را هر دقیقه اجرا می‌کند. scheduler در 01:15 جلسات را تولید، در 01:45 backup رمزگذاری‌شده را ایجاد و هر دقیقه heartbeat را تازه می‌کند.
- `BACKUP_ENCRYPTION_PASSWORD` یک secret مستقل و اجباری Production است و باید خارج از همان سرور نگهداری شود.
- پس از backup، `php artisan app:backup-verify /private/path/gym_backup_YYYYMMDD_HHMMSS.zip` اجرا شود. این فرمان checksum، رمزگشایی و سلامت دیتابیس را بدون دست‌زدن به دیتابیس جاری می‌آزماید.
- تصاویر پیشرفت و داده پزشکی در `storage/app/private/gyms/{gym_id}` هستند و نباید با storage symlink عمومی شوند.

## چک‌لیست smoke

- health endpoint و اتصال DB
- login/logout و CSRF
- انتخاب باشگاه مجاز و رد باشگاه غیرمجاز
- route ماژول غیرفعال
- صفحه dashboard، member list و pagination
- ایجاد یک job آزمایشی و مصرف توسط cron
- writable storage، ارسال mail و ثبت log

## منابع رسمی

- [Laravel 13 Deployment](https://laravel.com/docs/13.x/deployment)
- [Laravel 13 Release Notes and Support Policy](https://laravel.com/docs/13.x/releases)
