# ADR 0007: Platform Admin و مدیریت Entitlement باشگاه

**وضعیت:** پذیرفته‌شده

## تصمیم

Platform Admin یک هویت پلتفرمی مستقل با `users.is_platform_admin`، Gate `access-platform` و middleware مرکزی است. این سطح هیچ bypass برای داده tenant-owned ندارد.

مالک اولیه Gym با `gyms.owner_id` ثبت می‌شود، اما دسترسی مالک همچنان به membership فعال در `gym_user` نیاز دارد. ساخت Gym، مالک، membership، Branch پیش‌فرض و entitlementها در یک transaction انجام می‌شود.

`gyms`، `dashboard` و `settings` entitlementهای پایه و غیرقابل غیرفعال‌سازی در این فازند. سایر entitlementها فقط در صورت کامل بودن dependencyها sync می‌شوند.

## پیامد

پنل پلتفرم بدون Gym Context کار می‌کند، ولی Platform Admin برای ورود به route tenant باید مانند هر کاربر membership و انتخاب معتبر داشته باشد. هیچ Admin package یا permission system عمومی اضافه نمی‌شود.
