# ماژول‌ها

## قرارداد manifest

هر ماژول یک `module.php` با این قرارداد دارد:

```php
[
    'key' => 'members',
    'name' => 'اعضا',
    'description' => 'مدیریت اعضای باشگاه',
    'version' => '1.0.0',
    'dependencies' => ['gyms'],
    'default_enabled' => true,
    'navigation' => [],
    'permissions' => [],
    'routes' => [],
    'widgets' => [],
]
```

Registry هنگام boot یکتایی key و permission، وجود dependency و نبود cycle را validate می‌کند. دیتابیس فقط catalog sync‌شده و entitlement هر gym را نگه می‌دارد؛ manifest منبع حقیقت metadata است.

## کنترل غیرفعال بودن

ماژول غیرفعال:

- در navigation و dashboard widget دیده نمی‌شود.
- route وب/API آن middleware entitlement را رد می‌کند.
- permissionهای آن در فرم تخصیص نمایش داده نمی‌شود.
- scheduled command، listener اثرگذار، notification و queued job آن no-op امن/رد می‌شود.
- داده‌اش حفظ می‌شود و با فعال‌سازی دوباره قابل استفاده است.

غیرفعال‌سازی در صورت وجود dependent فعال رد می‌شود. فعال‌سازی ابتدا dependencyها را بررسی می‌کند؛ فعال‌سازی خودکار فقط اگر در ADR تصویب شود.

در Foundation مدیریت پلتفرم، `gyms`، `dashboard` و `settings` entitlementهای پایه و اجباری‌اند. سایر ماژول‌ها فقط entitlement می‌گیرند و Feature تجاری آن‌ها هنوز وجود ندارد. به‌روزرسانی، تمام keyها را با Registry تطبیق می‌دهد، dependency ناقص را رد می‌کند، در transaction انجام می‌شود و cache درون‌درخواستی `ModuleManager` را پاک می‌کند.

## گراف فاز اول

```text
authentication
└── gyms
    ├── dashboard
    ├── settings
    ├── module-management
    ├── roles-and-permissions
    └── members
        └── memberships
```

`authentication` و `gyms` قابلیت‌های پایه‌اند و entitlement تجاری قابل خاموش‌شدن ندارند. `dashboard` پوسته‌ای است که widgetها را از Registry جمع می‌کند و نباید به مدل داخلی ماژول تجاری وابسته شود.

## مسئولیت و API داخلی

| ماژول | مالکیت | API داخلی نمونه |
|---|---|---|
| Authentication | login، password، session | current user |
| Gyms | gym، membership امنیتی، context، default branch | resolve/switch gym |
| Dashboard | layout و widget aggregation | register/render widget |
| Members | پرونده عضو و archive | find member summary |
| Memberships | plan و دوره عضویت | active membership status |
| Settings | تنظیمات typed باشگاه | read/update setting |
| Module Management | registry sync و entitlement | isEnabled/enable/disable |
| Roles & Permissions | role، assignment و evaluation | allows(permission) |
| Coaches | پرونده مربی، انتساب به عضو و اتصال حساب ورود | coach roster |
| Wellness | تغذیه/تمرین، برنامه‌ها، اندازه‌گیری، عکس پیشرفت و درخواست مربی | member wellness record |
| Notifications | اعلان‌های درون‌برنامه‌ای tenant-scoped | notify user |

هیچ ماژولی model داخلی ماژول دیگر را مستقیم query نمی‌کند؛ برای read ساده Contract/DTO و برای واکنش غیرهمزمان Domain Event استفاده می‌شود.

## Entitlement در برابر Authorization

- **Entitlement:** آیا باشگاه این قابلیت را خریده/فعال کرده است؟
- **Role:** بسته‌ای باشگاه‌محور از مسئولیت‌های کاربر.
- **Permission:** اجازه granular برای operation.
- **Policy:** اجازه روی رکورد مشخص با توجه به state و ownership.

داشتن `members.create` بدون entitlement ماژول members کافی نیست؛ فعال بودن ماژول نیز بدون permission کافی نیست.

## permissionهای اولیه

- `dashboard.view`
- `members.view|create|update|archive`
- `memberships.view|create|update|archive`
- `settings.view|update`
- `modules.view|manage`
- `roles.view|create|update|archive|assign`

permission جدید باید متعلق به یک module، ترجمه‌پذیر، seed/sync idempotent و دارای تست مثبت و منفی باشد.

## افزودن ماژول آینده

1. تعریف مرز و owner داده
2. ثبت manifest و dependency یک‌طرفه
3. migrationهای tenant-aware
4. Contractهای حداقلی
5. middleware entitlement و permissionها
6. تست registry، cycle، disabled behavior و cross-tenant
7. مستندکردن rollout/rollback
