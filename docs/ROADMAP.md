# نقشه راه

هر فاز باید کوچک، deployable و دارای تست باشد. عبور از فاز بدون معیار Done ممنوع است.

## فاز 0 — تثبیت تصمیم‌ها و اسکلت

کارها: بررسی واقعی cPanel، انتخاب Laravel/PHP، ایجاد ADRهای پایه، scaffold Laravel/Livewire/Tailwind، CI، lint/test و health route.

**Done:** نسخه‌ها قفل؛ build از clone تمیز موفق؛ artifact بدون Node در host اجرا؛ ADRهای 1 تا 4 تصویب؛ هیچ Feature تجاری وجود ندارد.

## فاز 1 — Core tenancy

کارها: users، gyms، gym_user، default branch، GymContext، resolver، scope fail-closed، gym switch و audit پایه.

**Done:** تست دو/چندباشگاهی مثبت و منفی؛ query بدون context exception؛ route binding scoped؛ ایجاد gym اتمیک و دارای default branch؛ bypass پلتفرمی audit می‌شود.

## فاز 2 — Registry و entitlement

کارها: manifest contract، registry validation، module sync، gym_modules، middleware و navigation filtering.

**Done:** duplicate/cycle/dependency تست شده؛ ماژول غیرفعال route/API/widget/job/permission UI ندارد؛ cache invalidation درست؛ فعال/غیرفعال‌سازی audit می‌شود.

## فاز 3 — Role و Permission

کارها: roleهای پیش‌فرض و سفارشی، permission catalog، assignment، Gate/Policy و UI مدیریت.

**Done:** دسترسی حاصل تقاطع membership + entitlement + permission است؛ role رشته‌ای در view/controller بررسی نمی‌شود؛ cross-gym assignment رد؛ تست همه roleهای پیش‌فرض موجود است.

## فاز 4 — Design System و پوسته

کارها: RTL shell، sidebar/header، dark mode، فرم، modal/toast، table، stateها، command palette و formatter فارسی.

**Done:** mobile/desktop و light/dark بازبینی؛ keyboard-only و WCAG AA پایه؛ همه متن‌ها در translation؛ جدول server-paginated؛ کاهش motion رعایت شده.

## فاز 5 — Members

کارها: member list/search/filter، create/update، archive، policy، audit و query object.

**Done:** validation و IDOR tests؛ هیچ نشت tenant؛ pagination و ایندکس با EXPLAIN؛ query budget ثبت؛ empty/loading/error states؛ حذف مستقیم وجود ندارد.

## فاز 6 — Memberships

کارها: planها، ایجاد/ویرایش/بایگانی دوره عضویت، وضعیت و انقضا.

**Done:** قواعد تاریخ و status تست؛ روابط هم‌باشگاهی enforce؛ عملیات چندجدولی transaction؛ listها paginated؛ permission/entitlement منفی تست شده.

## فاز 7 — Settings و Dashboard

کارها: تنظیمات typed، widget aggregation و خلاصه‌های کم‌هزینه.

**Done:** widget ماژول غیرفعال غایب؛ cache tenant-aware و invalidation تست؛ dashboard query budget دارد؛ تنظیم حساس audit می‌شود.

## فاز 8 — آمادگی Production

کارها: rate limit، upload hardening، backup/restore drill، cron queue، optimize، logging، smoke tests و runbook.

**Done:** deploy آزمایشی روی cPanel واقعی؛ rollback کد و restore DB تمرین؛ debug خاموش؛ queue/scheduler پایدار؛ اسکن dependency و تست امنیتی بحرانی سبز.

## معیار Done مشترک

- acceptance criteria و تست Unit/Feature/Architecture سبز
- تست cross-tenant برای هر read/write باشگاهی
- authorization سمت سرور و تست مسیر منفی
- migration forward-safe و rollback/runbook متناسب
- ترجمه فارسی، RTL، keyboard و stateهای UI
- pagination، eager loading و query budget
- audit برای عملیات حساس
- مستندات و ADRهای متاثر به‌روز
- review و smoke test بدون regression شناخته‌شده بحرانی

## ترتیب ADR

پیش از فاز 1: نسخه framework، modularity، tenancy و Gym Context.  
پیش از فاز 2: Registry/entitlement.  
پیش از فاز 3: authorization.  
پیش از فاز 4: تاریخ/مبلغ/فونت.  
پیش از فاز 8: queue/cache/deployment/retention.

## ریسک‌های باقی‌مانده برای discovery

- قابلیت واقعی PHP CLI، Cron، symlink و تغییر document root روی میزبان نهایی
- سقف منابع و timeoutهای cPanel
- قواعد تجاری دقیق عضویت، تمدید و هم‌پوشانی
- سیاست data retention، حریم خصوصی و محل نگهداری backup
- مدل قیمت‌گذاری و منبع حقیقت entitlement

## مسیر محصول White-label پس از انتشار پایه

- پرتال عضو و API نسخه‌بندی‌شده برای اپلیکیشن موبایل
- adapter درگاه پرداخت با callback امضاشده، idempotency و reconciliation
- CRM سرنخ، قیف فروش و یادآوری قابل اتصال به پیامک/واتساپ
- کنترل تردد سخت‌افزاری از طریق contract مستقل vendor
- automationهای retention، تولد، غیبت و تمدید با رضایت‌نامه ارتباطی
- marketplace قالب‌ها؛ همه قالب‌ها token-based و بدون fork مشتری
