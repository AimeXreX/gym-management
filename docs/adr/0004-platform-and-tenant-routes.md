# ADR 0004: تفکیک Platform Route و Tenant Route

**وضعیت:** پذیرفته‌شده

## تصمیم

routeهای `account.*` به Gym Context نیاز ندارند و شامل logout، selector و no-gym هستند. routeهای `platform.*` زیر `/platform` فقط برای مدیر پلتفرم‌اند و Gym Context ندارند. routeهای `tenant.*` پس از auth از `gym.context` و entitlement عبور می‌کنند.

## پیامد

کاربر بدون Gym loop یا خطای 500 نمی‌گیرد و routeهای tenant بدون Context قابل اجرا نیستند.
