# معماری

## سبک

یک **Modular Monolith** با یک runtime، یک repository و یک دیتابیس Shared Schema انتخاب می‌شود. Core فقط زیرساخت عمومی را فراهم می‌کند و از ماژول‌های تجاری اطلاعی ندارد. ارتباط میان ماژول‌ها از Contract، Action صریح و Domain Event درون‌پردازه‌ای انجام می‌شود.

## لایه‌ها

```text
HTTP / Livewire / Console
        ↓
Middleware (auth → gym context → module → authorization)
        ↓
Module Application (Actions, Queries, DTOs)
        ↓
Module Domain (rules, contracts, events)
        ↓
Infrastructure (Eloquent, queue, cache, mail, filesystem)
```

Controller و Livewire component فقط ورودی را validate، authorization را درخواست و Action/Query را فراخوانی می‌کنند.

## ساختار پوشه پیشنهادی

```text
app/
  Core/
    Tenancy/        # GymContext, resolver, scope, middleware
    Modules/        # registry, entitlement contract
    Authorization/  # permission evaluator, gates
    Audit/
    Support/
  Modules/
    Authentication/
    Gyms/
    Dashboard/
    Members/
    Memberships/
    Settings/
    ModuleManagement/
    RolesPermissions/
      Application/{Actions,Queries,DTOs}/
      Domain/{Contracts,Events,Exceptions}/
      Infrastructure/{Models,Listeners}/
      Presentation/{Http,Livewire,Policies,Requests}/
      module.php
bootstrap/
config/
database/{factories,migrations,seeders}/
lang/fa/
resources/{css,js,views}/
routes/
tests/{Architecture,Feature,Unit}/
docs/adr/
```

پوشه‌های خالی از ابتدا ساخته نمی‌شوند؛ ساختار با نیاز واقعی هر ماژول رشد می‌کند.

## Gym Context و جداسازی داده

`GymContext` یک سرویس request-scoped و تنها API دریافت Gym جاری است. شناسه انتخاب‌شده توسط `GymSelection` در Session می‌ماند، اما Session منبع اعتماد نیست. `GymContextResolver` در هر request عضویت فعال کاربر و فعال بودن Gym را از دیتابیس بررسی می‌کند و فقط سپس context را تنظیم می‌کند.

Auto-select وجود ندارد. کاربر دارای Gym ولی بدون انتخاب معتبر به route پلتفرمی انتخاب Gym، و کاربر بدون عضویت فعال به صفحه `no-gym` هدایت می‌شود. انتخاب stale/غیرمجاز از Session پاک می‌شود. middleware resolver در priority پیش از implicit route binding اجرا می‌شود تا binding مدل tenant-owned همیشه scoped باشد.

مدل tenant-owned از trait مشترک استفاده می‌کند:

- Global Scope فقط وقتی context معتبر است `gym_id` را اعمال می‌کند.
- نبود context باعث exception می‌شود، نه query بدون scope.
- هنگام create، `gym_id` از context ست می‌شود و ورودی کاربر پذیرفته نمی‌شود.
- bypass فقط با API نام‌گذاری‌شده پلتفرمی، audit و تست مجاز است.
- route model binding پس از scope انجام می‌شود تا IDOR مسدود شود.

Queryهای cross-gym برای مدیر پلتفرم از مدل/Query Object مجزا استفاده می‌کنند؛ حذف دستی global scope در کد تجاری ممنوع است.

### مرز Routeها

- routeهای `account.*` فقط به authentication وابسته‌اند: خروج، انتخاب Gym و صفحه no-gym.
- routeهای `/platform` با نام `platform.*` فقط از Gate و middleware مرکزی Platform Admin عبور می‌کنند و Gym Context نمی‌خواهند.
- routeهای tenant-level علاوه بر authentication الزاماً `gym.context` و در صورت لزوم `module:*` دارند.
- Platform Admin برای route یا مدل tenant-owned bypass ندارد.

## Platform Admin

تشخیص مدیر پلتفرم با boolean مستقل `users.is_platform_admin` و Gate به نام `access-platform` انجام می‌شود. Controller و View مستقیماً این فیلد را بررسی نمی‌کنند. ساخت باشگاه یک workflow تراکنشی صریح است؛ فقط برای ایجاد Branch پیش‌فرض، Context همان Gym جدید موقتاً برقرار و بلافاصله پاک می‌شود.

## زنجیره دسترسی

هر درخواست باشگاهی به‌ترتیب بررسی می‌شود:

1. authentication
2. active gym membership
3. current `GymContext`
4. enabled module و dependencyهای آن
5. permission از roleهای همان membership
6. Policy برای مالکیت/وضعیت خود رکورد

مخفی کردن UI کنترل امنیتی نیست؛ سرور همیشه همین زنجیره را enforce می‌کند.

## Module Registry

تعریف ثابت ماژول در `module.php` و state باشگاهی در دیتابیس است. Registry در boot همه manifestها را validate می‌کند و metadata کم‌تغییر را cache می‌کند. route هر ماژول داخل middleware entitlement ثبت می‌شود. command/job/listener ماژول باید پیش از اثر جانبی entitlement را دوباره بررسی کند.

Registry اکنون یکتایی key، وجود dependency و نبود cycle را fail-closed بررسی می‌کند. `ModuleManager` entitlement خود ماژول و تمام dependencyها را برای Gym جاری کنترل می‌کند. cache درون‌درخواستی با کلید `gym_id:module_key` است تا تعویض Gym نتیجه قبلی را reuse نکند.

تصمیم‌های تثبیت‌شده این مرحله در [ADRها](adr/) ثبت شده‌اند.

## تراکنش، event و queue

- عملیات چندجدولی سازگار در transaction انجام می‌شود.
- event بیرونی/queued پس از commit dispatch می‌شود.
- queue پیش‌فرض Database است؛ cron هر دقیقه `queue:work --stop-when-empty` را با محدودیت زمان اجرا می‌کند.
- job شامل `gym_id` و شناسه actor است و idempotent طراحی می‌شود.
- notification ماژول غیرفعال ارسال نمی‌شود.

## امنیت و مشاهده‌پذیری

- Form Request/Livewire validation، CSRF، escaping پیش‌فرض Blade و CSP مرحله‌ای
- rate limit برای login، دعوت و عملیات حساس
- session cookie با Secure، HttpOnly و SameSite مناسب
- upload با allowlist MIME/size، نام تصادفی و ذخیره خارج از public
- audit append-only برای تغییر entitlement، role، permission، gym switch و archive
- log شامل request ID، gym ID و actor ID؛ بدون secret یا داده حساس
- health endpoint سبک برای DB، cache و writable storage

## کارایی

pagination اجباری، eager loading انتخابی، query budget برای صفحات کلیدی، strict lazy-loading در توسعه، ایندکس مرکب با `gym_id` و cache کلیدگذاری‌شده با gym و نسخه entitlement. cache هرگز نباید کلید مشترک tenant داشته باشد.

## ریسک‌های معماری

| ریسک | اثر | کنترل |
|---|---|---|
| فراموش شدن scope tenancy | نشت بحرانی داده | fail-closed scope، تست cross-tenant، ممنوعیت `withoutGlobalScopes` |
| bypass در job/CLI | عملیات روی tenant اشتباه | gym در payload، middleware job و exception بدون context |
| قاطی شدن entitlement و permission | دسترسی ناخواسته | سه سرویس و سه نوع تست مستقل |
| dependency چرخه‌ای ماژول‌ها | monolith درهم‌تنیده | graph validation و architecture test |
| محدودیت worker در cPanel | تأخیر job | cron + stop-when-empty، jobهای کوتاه و retry امن |
| نسخه قدیمی PHP میزبان | عدم نصب Laravel 13 | preflight و fallback ثبت‌شده Laravel 12 |
| cache آلوده بین tenantها | نشت داده | key namespace اجباری و تست |
| رشد role/permission query | کندی پنل | eager load/cache نسخه‌دار و invalidation صریح |
| migration بزرگ | downtime | migrationهای کوچک، expand/contract و backup |

## تصمیم‌های لازم برای ADR

فایل‌های ADR در `docs/adr/NNNN-title.md` ایجاد می‌شوند:

1. Laravel 13/PHP 8.3 و معیار fallback به Laravel 12
2. Modular Monolith و قواعد dependency
3. Shared Schema tenancy و fail-closed global scope
4. روش تعیین Gym Context و gym switching
5. مدل authorization باشگاهی و platform admin
6. manifest-based Module Registry و entitlement
7. Database Queue روی cron بدون Supervisor
8. File در برابر Database Cache
9. ذخیره UTC و نمایش تقویم شمسی
10. archive/soft-delete و retention audit
11. راهبرد asset build و atomic deployment در cPanel
12. راهبرد تست معماری، cross-tenant و query budget
