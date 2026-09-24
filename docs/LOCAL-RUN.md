# اجرای محلی و حساب‌های آزمایشی

## پیش‌نیازها

- PHP 8.3 یا جدیدتر با PDO SQLite/MySQL، mbstring، OpenSSL، tokenizer، XML، ctype، fileinfo و BCMath
- Composer 2 (یا Composer محلی قابل شناسایی توسط `scripts\composer.bat`)
- Node.js 20 یا جدیدتر و npm

در ویندوز، اگر PHP در PATH نیست، متغیر `GYM_PHP_PATH` را روی مسیر کامل `php.exe` تنظیم کنید. اسکریپت `scripts/artisan.bat` همچنین نصب محلی `.foundation-tools` را در دو چیدمان رایج پیدا می‌کند.

## نصب تازه برای تست محلی

```bat
copy .env.example .env
scripts\composer.bat install
scripts\artisan.bat key:generate
scripts\artisan.bat migrate:fresh --seed
npm ci
npm run build
scripts\artisan.bat serve
```

سپس `http://127.0.0.1:8000` را باز کنید.

تنظیم محلی پیش‌فرض از SQLite در `database/database.sqlite` استفاده می‌کند و به نصب MySQL نیاز ندارد. فایل `.env.production.example` برای سرور همچنان MySQL 8 را تنظیم می‌کند.

## اجرای روزمره پس از نصب

```bat
scripts\artisan.bat migrate
npm run build
scripts\artisan.bat serve
```

برای توسعه هم‌زمان frontend می‌توانید در پنجره دوم `npm run dev` را اجرا کنید. اجرای production به Node نیاز ندارد و از فایل‌های آماده `public/build` استفاده می‌کند.

## تست‌ها

```bat
scripts\artisan.bat test
```

برای آزمون مخرب MySQL فقط از دیتابیسی استفاده کنید که نام آن به `_test` ختم می‌شود:

```bat
scripts\test-mysql.bat
```

## حساب‌های آزمایشی

همه حساب‌ها رمز `password` دارند و فقط در محیط `local` یا با `ALLOW_TEST_ACCOUNTS=true` ساخته می‌شوند.

| نقش | ایمیل | کاربرد |
|---|---|---|
| مدیر پلتفرم | `admin@gym.test` | مدیریت باشگاه‌ها و entitlementها |
| مالک باشگاه | `demo@gym.test` | دسترسی کامل باشگاه |
| مدیر باشگاه | `manager@gym.test` | مدیریت عملیات باشگاه |
| پذیرش | `staff@gym.test` | اعضا، حضور و کلاس‌ها |
| حسابدار | `accountant@gym.test` | پرداخت، گزارش و کیف پول |
| مربی تمرین و تغذیه | `coach@gym.test` | داشبورد جامع و آزمایش هر دو حوزه |
| مربی فقط تغذیه | `nutrition@gym.test` | برنامه غذایی و آزمون منع ابزار تمرینی |
| ورزشکار | `member@gym.test` | پرتال عضو و درخواست مربی/برنامه |

برای آزمون دستی نقش دوگانه، با `coach@gym.test` وارد شوید. این حساب به عضو نمونه در هر دو حوزه تمرین و تغذیه منتسب است. برای کنترل منفی با `nutrition@gym.test` وارد شوید؛ لینک قالب‌های تمرینی نباید نمایش داده شود و باز کردن مستقیم آن باید 403 بدهد.

## نکته امنیتی

`migrate:fresh --seed` تمام داده‌های دیتابیس هدف را حذف می‌کند و فقط برای محیط محلی/تست است. در production فقط `php artisan migrate --force` اجرا شود و `ALLOW_TEST_ACCOUNTS` باید `false` بماند.
