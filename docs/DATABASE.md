# دیتابیس

## اصول

- MySQL با `utf8mb4` و timezone اتصال UTC.
- کلید اصلی bigint unsigned؛ UUID/ULID برای شناسه عمومی فقط پس از ADR.
- تمام جدول‌های tenant-owned دارای `gym_id`، foreign key و ایندکس مرکب هستند.
- نام‌گذاری انگلیسی، timestampهای استاندارد و مبلغ به‌صورت integer در کوچک‌ترین واحد انتخاب‌شده.
- foreign key از حذف داده مرجع جلوگیری می‌کند؛ داده مهم archive/soft-delete می‌شود.

## مدل اولیه

### پلتفرم و tenancy

| جدول | ستون‌های مهم | قیود/ایندکس |
|---|---|---|
| `users` | `id`, `name`, `email`, `password`, `is_platform_admin`, `locale`, `last_login_at` | unique email |
| `gyms` | `id`, `owner_id`, `name`, `slug`, `status`, `timezone`, `locale`, `currency`, `settings_json` | unique slug، index `(owner_id,status)` |
| `gym_user` | `id`, `gym_id`, `user_id`, `status`, `joined_at` | unique `(gym_id,user_id)`, index `(user_id,status)` |
| `gym_invitations` | `id`, `gym_id`, `email`, `token_hash`, `expires_at`, `accepted_at` | unique token hash، index `(gym_id,email,status)` |
| `branches` | `id`, `gym_id`, `name`, `is_system_default`, `status`, `deleted_at` | index `(gym_id,status)`، فقط یک default با کنترل برنامه/transaction |

`gym_user` همان membership امنیتی کاربر در tenant است و با عضویت تجاری مشتری در `member_memberships` تفاوت دارد.

`owner_id` مالک اولیه و صریح Gym را برای عملیات پلتفرمی مشخص می‌کند؛ دسترسی tenant همچنان فقط از `gym_user` حاصل می‌شود. حذف User مقدار owner را null می‌کند و Gym حذف نمی‌شود.

### ماژول و دسترسی

| جدول | ستون‌های مهم | قیود/ایندکس |
|---|---|---|
| `modules` | `id`, `key`, `version`, `metadata_hash` | unique key |
| `gym_modules` | `gym_id`, `module_id`, `enabled`, `config_json`, `enabled_at` | PK/unique `(gym_id,module_id)` |
| `roles` | `id`, `gym_id`, `key`, `name`, `is_system`, `deleted_at` | unique `(gym_id,key)` |
| `permissions` | `id`, `module_id`, `key`, `name` | unique key |
| `permission_role` | `role_id`, `permission_id` | composite PK |
| `gym_user_role` | `gym_user_id`, `role_id` | composite PK؛ service باید هم‌باشگاهی بودن را enforce کند |

`permissions` catalog سراسری manifestهاست؛ قابل تخصیص بودن آن به فعال بودن module در همان gym وابسته است.

### اعضا و عضویت‌های تجاری

| جدول | ستون‌های مهم | قیود/ایندکس |
|---|---|---|
| `members` | `id`, `gym_id`, `branch_id`, `member_no`, `first_name`, `last_name`, `mobile`, `email`, `birth_date`, `status`, `archived_at` | unique `(gym_id,member_no)`, index `(gym_id,status,id)`, `(gym_id,mobile)` |
| `membership_plans` | `id`, `gym_id`, `name`, `duration_days`, `price_amount`, `status`, `archived_at` | index `(gym_id,status)` |
| `member_memberships` | `id`, `gym_id`, `member_id`, `plan_id`, `starts_on`, `ends_on`, `status`, `price_amount`, `archived_at` | index `(gym_id,member_id,status)`, `(gym_id,status,ends_on)` |

`gym_id` حتی در جداولی که از رابطه قابل استنتاج است عمداً تکرار می‌شود تا scope، partition ذهنی و query/index کارآمد باشد. Actionها سازگاری gym تمام foreign keyها را پیش از write بررسی می‌کنند.

### عملیات و زیرساخت

| جدول | کاربرد |
|---|---|
| `audit_logs` | `gym_id` nullable، `user_id`، event، auditable، IP، user agent و metadata پاک‌سازی‌شده |
| `jobs`, `job_batches`, `failed_jobs` | Database Queue |
| `cache`, `cache_locks` | فقط اگر Database Cache انتخاب شود |
| `sessions` | در صورت انتخاب Database Session |
| `notifications` | notificationهای persisted، با `gym_id` |

## روابط اصلی

```text
users ──< gym_user >── gyms ──< branches
                 └──< gym_user_role >── roles ──< permission_role >── permissions
gyms ──< gym_modules >── modules ──< permissions
gyms ──< members ──< member_memberships >── membership_plans
```

## قواعد migration

- ابتدا table/column nullable یا سازگار اضافه، سپس backfill محدود، سپس constraint؛ حذف در release بعد.
- migration نباید dataset کامل را در حافظه بگیرد.
- ایندکس هر query کلیدی با ترتیب `gym_id` سپس filter/sort طراحی و با `EXPLAIN` تأیید شود.
- تغییر destructive فقط با backup تأییدشده و rollback عملیاتی.
- seeder مجوزها و module catalog idempotent است.

## تضمین جداسازی

1. foreign key و uniqueهای tenant-aware
2. scope مرکزی fail-closed
3. Policy و route binding scoped
4. write service با بررسی gym روابط
5. تست دو باشگاه برای هر Query/Action
6. static/architecture test برای مدل tenant-owned بدون trait

MySQL row-level security بومی مورد اتکا نیست؛ تضمین اصلی در application و تست است.

## مدل اثبات جداسازی

`tenant_probes` و مدل داخلی `App\Core\Tenancy\Testing\TenantProbe` فقط برای اثبات مستقیم Scope، create امن، immutability مالکیت و route model binding ساخته شده‌اند. این مدل در UI یا دامنه محصول استفاده نمی‌شود و داده production برای آن ایجاد نمی‌گردد.

Migrationهای Foundation از bigint unsigned، foreign keyهای صریح، JSON nullable، stringهای کوتاه برای status و نام indexهای زیر محدودیت MySQL استفاده می‌کنند. اجرای واقعی MySQL در این محیط انجام نشده است؛ روش تکرارپذیر آن در [DEPLOYMENT](DEPLOYMENT.md) آمده است.
