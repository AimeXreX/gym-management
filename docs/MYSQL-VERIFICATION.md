# MySQL 8 verification

این بسته برای اجرای امن روی MySQL 8 یا MariaDB سازگار آماده شده است. در ۱۷ اوت ۲۰۲۶ روی MySQL 8.0.46 (Ubuntu 22.04) اجرا و تأیید شد: migration، seed و هر ۹۱ تست با ۲۳۷ assertion سبز شدند.

## پیش‌نیاز

یک دیتابیس اختصاصی که نامش به `_test` ختم می‌شود (پیشنهاد: `gym_web_test`) و یک کاربر محدود به همان دیتابیس.

```sql
CREATE DATABASE IF NOT EXISTS gym_web_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'gym_test'@'localhost' IDENTIFIED BY 'یک-رمز-قوی';
CREATE USER IF NOT EXISTS 'gym_test'@'127.0.0.1' IDENTIFIED BY 'یک-رمز-قوی';
GRANT ALL PRIVILEGES ON gym_web_test.* TO 'gym_test'@'localhost';
GRANT ALL PRIVILEGES ON gym_web_test.* TO 'gym_test'@'127.0.0.1';
FLUSH PRIVILEGES;
```

## اجرا

1. یک فایل `.env.testing` بسازید (مثل `.env.testing` محلی که gitignore شده) با `DB_CONNECTION=mysql`، `DB_DATABASE` (پسوند `_test`)، `DB_USERNAME`/`DB_PASSWORD` محدود، `APP_ENV=testing` و `ALLOW_TEST_ACCOUNTS=true`.
2. اجرا:
   - لینوکس/macOS: `bash scripts/test-mysql.sh`
   - ویندوز: `scripts\test-mysql.bat`

هر دو اسکریپت پیش از `migrate:fresh` نام دیتابیس را کنترل می‌کنند (باید به `_test` ختم شود و در لیست سیاه نباشد)، cache را پاک می‌کنند، migration/seed و تمام تست‌ها را اجرا می‌کنند و در هر شکست exit code غیرصفر برمی‌گردانند. هرگز آن‌ها را با credential دیتابیس production اجرا نکنید.

## نکته درباره پیکربندی PHPUnit

`phpunit.xml.dist` به‌عمد دیتابیس تست را SQLite درون‌حافظه می‌کند تا اجرای محلی سریع و بدون وابستگی باشد. برای اینکه تست‌ها واقعاً روی MySQL اجرا شوند، اسکریپت‌ها PHPUnit را مستقیم با `--configuration=phpunit.mysql.xml` صدا می‌زنند؛ این پیکربندی `DB_CONNECTION`/`DB_DATABASE` را بازنویسی نمی‌کند و مقادیر را از `.env.testing` می‌گیرد. (دستور `artisan test` به‌خاطر collision همیشه پیکربندی پیش‌فرض خودش را تزریق می‌کند و نمی‌توان پیکربندی دلخواه به آن داد.)

## بازبینی استاتیک (تأییدشده)

foreign keyها از unsigned big integerهای Laravel استفاده می‌کنند؛ مبالغ `decimal(14,2)` هستند؛ JSON فقط در `settings_json` و metadata استفاده شده؛ indexهای مرکب زیر محدودیت MySQL 8 هستند؛ route binding با tenant global scope انجام می‌شود؛ عملیات پرداخت و عضویت transaction دارند؛ soft delete روی منابع lifecycle فعال است.
