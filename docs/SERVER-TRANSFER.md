# انتقال و راه‌اندازی روی سرور

این راهنما برای هاست لینوکسی/cPanel با PHP 8.3+، MySQL 8، Cron و HTTPS نوشته شده است. Production به Node، Docker، Redis، Supervisor یا دسترسی root نیاز ندارد.

## ۱. ساخت بسته روی سیستم توسعه

```powershell
scripts\artisan.bat test
pnpm run build
powershell -ExecutionPolicy Bypass -File scripts\build-release.ps1
```

فایل ZIP و SHA256 در `artifacts/` ساخته می‌شوند. بسته شامل `vendor`، assetهای buildشده، مستندات معماری و `AGENTS.md` است تا توسعه‌دهنده یا عامل AI بعدی قواعد tenancy و نقش‌ها را بداند. `.env`، دیتابیس محلی، تصاویر خصوصی، لاگ، cache، test suite، `node_modules` و `.git` وارد بسته نمی‌شوند.

## ۲. آماده‌سازی هاست

1. دامنه یا subdomain و SSL را فعال کنید.
2. PHP 8.3 یا بالاتر را انتخاب و extensionهای PDO MySQL، mbstring، OpenSSL، tokenizer، XML، ctype، fileinfo، BCMath و ZIP را فعال کنید.
3. یک دیتابیس MySQL و کاربر اختصاصی با رمز قوی بسازید.
4. پوشه‌ای خارج از `public_html` مانند `/home/ACCOUNT/apps/gym/releases/1` ایجاد و ZIP را همان‌جا extract کنید.
5. Document Root دامنه را روی `/home/ACCOUNT/apps/gym/releases/1/public` بگذارید. کل پروژه را داخل `public_html` قرار ندهید.

## ۳. تنظیم محیط

`.env.production.example` را به `.env` کپی و مقادیر APP_URL، APP_KEY، DB، MAIL، BACKUP_PATH و BACKUP_ENCRYPTION_PASSWORD را پر کنید. APP_KEY فقط بار اول با `php artisan key:generate` ساخته و میان releaseها ثابت نگه داشته شود. رمز backup را خارج از همان سرور نیز نگهداری کنید.

```bash
cp .env.production.example .env
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan app:production-check
```

Seeder تست روی Production به‌صورت پیش‌فرض اجرا نمی‌شود. اگر برای پذیرش اولیه به حساب‌های نقش نیاز دارید، موقتاً `ALLOW_TEST_ACCOUNTS=true` بگذارید، `php artisan config:clear` و سپس `php artisan db:seed --force` را اجرا کنید. بلافاصله مقدار را به `false` برگردانید و `php artisan optimize` را اجرا کنید. این کار هشت حساب `@gym.test`، یک باشگاه و داده حداقلی لازم را می‌سازد؛ برای سامانه عمومی رمزها را تغییر دهید یا حساب‌ها را غیرفعال کنید.

## ۴. دسترسی فایل‌ها

فقط `storage` و `bootstrap/cache` باید برای کاربر PHP writable باشند. `.env` باید خارج از web root و ترجیحاً با permission برابر 600 باشد. تصاویر پیشرفت در `storage/app/private` هستند و نباید symlink عمومی بگیرند.

## ۵. Cron

فقط این Cron هر دقیقه لازم است:

```cron
* * * * cd /home/ACCOUNT/apps/gym/current && /usr/local/bin/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

Scheduler خودش heartbeat، تولید جلسات و backup شبانه را اجرا می‌کند. مسیر PHP را از cPanel بگیرید.

## ۶. تست پس از انتقال

```bash
php artisan app:production-check
php artisan route:list
php artisan app:backup
php artisan app:backup-verify /PATH/TO/LATEST/gym_backup_YYYYMMDD_HHMMSS.zip
```

سپس `/health`، `/login`، پنل پلتفرم، انتخاب باشگاه، داشبورد مدیر و پرتال ورزشکار را بررسی کنید. `/health` پس از اولین backup و اجرای Cron باید HTTP 200 بدهد.

## ۷. انتشار نسخه بعدی و Rollback

هر نسخه را در release جدید extract کنید، `.env` و storage پایدار را متصل کنید، backup بگیرید، migration و optimize را اجرا کنید و سپس document root یا symlink `current` را تغییر دهید. release قبلی را تا پایان smoke test نگه دارید. در rollback فقط کد را به نسخه قبلی برگردانید؛ migrationهای داده‌ای را خودکار reverse نکنید.

## ۸. استقرار خودکار با `scripts/deploy.sh`

اسکریپت `scripts/deploy.sh` (که داخل بسته release هم می‌آید) مراحل استقرار را خودکار می‌کند و به root نیاز ندارد:

```bash
# استقرار یک release تازه (از داخل همان release، یا با مسیر آرچیو)
bash releases/1/scripts/deploy.sh --env-file ~/apps/gym/.env.production
bash releases/2/scripts/deploy.sh --base ~/apps/gym   # .env قبلی را نگه می‌دارد

# بازگشت به release قبلی
bash releases/2/scripts/deploy.sh --base ~/apps/gym --rollback
```

کاری که انجام می‌دهد: ساخت/نگهداری `.env` با APP_KEY پایدار، permissionهای `storage` و `bootstrap/cache` (و 600 برای `.env`)، backup دیتابیس پیش از migration، `migrate --force`، `storage:link`، ساخت cache (config/route/view)، اجرای `app:production-check` و جابه‌جایی اتمیک symlink «current». گزینه‌ها: `--env-file`، `--base`، `--php`، `--skip-backup`، `--no-optimize`. هرگز `migrate:fresh` یا seeder اجرا نمی‌کند و تا موفقیت production-check و backup، symlink را عوض نمی‌کند.
