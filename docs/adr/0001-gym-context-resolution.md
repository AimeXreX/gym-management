# ADR 0001: Resolve شدن Gym Context

**وضعیت:** پذیرفته‌شده

## تصمیم

Session فقط `current_gym_id` انتخابی را نگه می‌دارد. `GymContextResolver` در هر request آن را با عضویت فعال کاربر و Gym فعال تطبیق می‌دهد. Auto-select ممنوع است. انتخاب نامعتبر پاک و کاربر به selector یا no-gym هدایت می‌شود.

## پیامد

حذف عضویت یا غیرفعال شدن Gym در request بعدی اثر می‌کند. هزینه یک query اعتبارسنجی در هر request tenant پذیرفته می‌شود.
