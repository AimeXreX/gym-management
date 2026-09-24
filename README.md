# Gym SaaS MVP

[![Laravel](https://img.shields.io/badge/Laravel-13.x-red?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.3-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![Livewire](https://img.shields.io/badge/Livewire-4-fb70a9?logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-38bdf8?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![MySQL](https://img.shields.io/badge/MySQL-8-4479a1?logo=mysql&logoColor=white)](https://www.mysql.com)
[![Tests](https://img.shields.io/badge/tests-187%20passed-22c55e)]()
[![License](https://img.shields.io/badge/license-MIT-4f46e5)]()

سامانه فارسی و RTL مدیریت چندباشگاهی با Laravel 13، شامل مدیریت شعب، اعضا، عضویت، پرداخت، حضور، مربی، کلاس، گزارش، CSV، QR و تنظیمات عملیاتی.

## نصب محلی

```bat
copy .env.example .env
set GYM_PHP_PATH=C:\path\to\php.exe
scripts\composer.bat install
scripts\artisan.bat key:generate
scripts\artisan.bat migrate:fresh --seed
npm ci
npm run build
scripts\artisan.bat serve
```

حساب‌های demo فقط در محیط local ساخته می‌شوند:

| نقش | ایمیل | رمز |
|---|---|---|
| مدیر پلتفرم | `admin@gym.test` | `password` |
| مالک باشگاه | `demo@gym.test` | `password` |
| پذیرش | `staff@gym.test` | `password` |

## جریان‌های محصول

- شعب: ایجاد، ویرایش، فعال/آرشیو و تعیین پیش‌فرض.
- اعضا: پرونده، عضویت، پرداخت، تصویر، QR، حضور، CSV import/export.
- مربی و کلاس: lifecycle، برنامه هفتگی، جلسه، ثبت‌نام و لغو.
- تنظیمات: timezone، currency، شعبه پیش‌فرض، پیشوند کد، هشدار انقضا و سیاست پذیرش.
- گزارش‌ها: اعضا، حضور، مالی و کلاس‌ها با فیلتر و CSV.

## تست

راهنمای به‌روز اجرای محلی، دستورها و همه حساب‌های آزمایشی در [docs/LOCAL-RUN.md](docs/LOCAL-RUN.md) قرار دارد.

- اجرای سریع (SQLite درون‌حافظه): `php artisan test`
- تأیید MySQL 8 (دیتابیس اختصاصی `_test`): `bash scripts/test-mysql.sh` — جزئیات در [docs/MYSQL-VERIFICATION.md](docs/MYSQL-VERIFICATION.md)
- راهنمای تست دستی و حساب‌های نمونه: [docs/TESTING.md](docs/TESTING.md)

## مستندات

- معماری و ریسک‌ها: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- دیتابیس و tenancy: [docs/DATABASE.md](docs/DATABASE.md)
- ماژول‌ها و مجوزها: [docs/MODULES.md](docs/MODULES.md)
- انتشار و cPanel: [docs/RELEASE.md](docs/RELEASE.md)
- انتقال به سرور: [docs/SERVER-TRANSFER.md](docs/SERVER-TRANSFER.md)

## محدودیت‌های پس از MVP

اسکن دوربین، اعلان پایدار، همگام‌سازی کامل آفلاین، گواهی کامل WCAG و گزارش پیشرفته حسابداری.
