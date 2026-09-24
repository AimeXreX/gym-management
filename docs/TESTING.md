# راهنمای تست دستی

همه حساب‌های نمونه رمز `password` دارند:

| نقش | ایمیل |
|---|---|
| مدیر پلتفرم | `admin@gym.test` |
| مدیر باشگاه | `demo@gym.test` |
| مدیر عملیاتی باشگاه | `manager@gym.test` |
| حسابدار | `accountant@gym.test` |
| پذیرش | `staff@gym.test` |
| مربی تمرین | `coach@gym.test` |
| مربی تغذیه | `nutrition@gym.test` |
| ورزشکار | `member@gym.test` |

پس از ورود، باشگاه «باشگاه آزمایشی» را انتخاب کنید.

## URLهای اصلی

| بخش | URL |
|---|---|
| ورود | `/login` |
| انتخاب باشگاه | `/gyms/select` |
| داشبورد | `/dashboard` |
| تغذیه، تمرین و حضور ورزشکار جاری | `/wellness` |
| پرونده یک ورزشکار برای مربی | `/wellness/{member_id}` |
| داشبورد مربی | `/coach-dashboard` |
| اعضا | `/members` |
| مربیان | `/coaches` |
| پذیرش و حضور | `/attendance/check-in` |
| کیف پول و شارژ | `/wallets` و `/wallets/{member_id}` |
| کلاس‌ها | `/classes` |
| طرح عضویت | `/membership-plans` |
| پرداخت‌ها | `/payments` |
| گزارش‌ها | `/reports` |
| شعب | `/branches` |
| کافه | `/cafe` |
| ظاهر و برند | `/appearance` |
| تنظیمات | `/settings` |
| مدیریت پلتفرم | `/platform` و `/platform/gyms` |
| سلامت سرویس | `/health` |

نکته: مسیرهای باشگاهی تنها پس از انتخاب باشگاه و فقط وقتی entitlement همان ماژول فعال باشد باز می‌شوند. ورزشکار نمونه به اولین عضو نمایشی و دو مربی نمونه به همان ورزشکار متصل شده‌اند.
