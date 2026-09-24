# رابط کاربری

## جهت طراحی

رابط فارسی، RTL، mobile-first، مینیمال و حرفه‌ای است. خوانایی و سرعت از تزئین مهم‌تر است. فونت فارسی self-hosted با subset و `font-display: swap` ارائه می‌شود؛ انتخاب و مجوز فونت در ADR/Design review قطعی خواهد شد.

## پوسته پنل

- Sidebar واکنش‌گرا: drawer در موبایل و ثابت/جمع‌شونده در دسکتاپ
- Header ساده: عنوان، تعویض باشگاه مجاز، جست‌وجو/Command palette، اعلان و پروفایل
- Breadcrumb برای عمق بیش از یک سطح
- navigation فقط از Registry ماژول‌های فعال و permissionهای کاربر ساخته می‌شود
- انتخابگر شعبه فقط وقتی ماژول branches فعال باشد

## Design tokens

tokenها با CSS custom properties تعریف می‌شوند: رنگ زمینه/سطح/متن/مرز/primary/danger/success، spacing، radius، shadow، typography و focus ring. Dark mode با class و ترجیح سیستم پشتیبانی می‌شود. کنتراست متن و کنترل‌ها حداقل WCAG 2.2 AA است.

## اجزای پایه

- Button، Link، Badge، Avatar، Card، Divider
- Input، Textarea، Select، Checkbox، Radio، Toggle، Date/Money input
- Form field یکپارچه با label، hint و error
- Modal، Confirm dialog، Toast و Command palette
- Table واکنش‌گرا با pagination سمت سرور
- filter panel جمع‌شونده و قابل reset
- Skeleton، Empty، Error و Permission-denied state

برای عملیات destructive، dialog نام عملیات و پیامد را روشن می‌گوید؛ focus داخل modal محبوس و پس از بستن به trigger برمی‌گردد.

## جدول‌ها

- dataset کامل به browser ارسال نمی‌شود.
- sort/filter/search سمت سرور و query string قابل share است.
- در موبایل ستون‌های فرعی مخفی یا هر ردیف به کارت تبدیل می‌شود.
- header، caption و نام قابل‌دسترسی برای actionها الزامی است.
- loading قبلی را بی‌دلیل پاک نمی‌کند و layout shift کم است.

## فارسی‌سازی

- همه رشته‌ها از فایل‌های `lang/fa` و کلیدهای دامنه‌ای می‌آیند؛ متن مستقیم در component ممنوع.
- اعداد برای نمایش با رقم فارسی format می‌شوند، اما input و payload canonical باقی می‌مانند.
- مبلغ با واحد صریح و formatter مرکزی؛ تبدیل ریال/تومان هرگز ضمنی نیست.
- تاریخ در DB میلادی/UTC و در UI شمسی با timezone باشگاه نمایش داده می‌شود.
- نام اشخاص و شماره عضویت در جست‌وجو normalize می‌شوند (ی/ي و ک/ك).

## دسترس‌پذیری و صفحه‌کلید

- HTML معنایی، ترتیب focus منطقی، skip link و focus visible
- label واقعی برای همه inputها؛ خطا با `aria-describedby`
- Escape برای بستن overlay، arrow key برای menu، میانبر palette با `Ctrl/Cmd+K`
- toast با live region مناسب و بدون ربودن focus
- target لمسی حداقل 44px و motion با `prefers-reduced-motion`

## بودجه تجربه و کارایی

- حداقل JavaScript؛ Alpine برای رفتار محلی و Livewire برای تعامل server-driven
- animation کوتاه و فقط transform/opacity
- تصویر WebP/AVIF در صورت پشتیبانی، ابعاد مشخص و lazy loading زیر fold
- از UI libraryهای متعدد و DOM بزرگ جلوگیری می‌شود
- stateهای loading/empty/error برای هر صفحه در معیار پذیرش هستند

