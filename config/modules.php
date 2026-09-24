<?php

return ['member_import_max_rows' => env('MEMBER_IMPORT_MAX_ROWS', 500), 'registry' => [
    ['key' => 'gyms', 'name' => 'باشگاه‌ها', 'description' => 'زیرساخت باشگاه جاری', 'version' => '1.0.0', 'dependencies' => [], 'default_enabled' => true],
    ['key' => 'dashboard', 'name' => 'داشبورد', 'description' => 'نمای عملیاتی باشگاه', 'version' => '2.0.0', 'dependencies' => ['gyms'], 'default_enabled' => true],
    ['key' => 'branches', 'name' => 'شعب', 'description' => 'مدیریت شعب باشگاه', 'version' => '2.0.0', 'dependencies' => ['gyms'], 'default_enabled' => false],
    ['key' => 'members', 'name' => 'اعضا', 'description' => 'مدیریت پرونده اعضا', 'version' => '2.0.0', 'dependencies' => ['branches'], 'default_enabled' => false],
    ['key' => 'membership_plans', 'name' => 'طرح‌های عضویت', 'description' => 'مدیریت تعرفه و مدت طرح‌ها', 'version' => '2.0.0', 'dependencies' => ['branches'], 'default_enabled' => false],
    ['key' => 'memberships', 'name' => 'عضویت‌ها', 'description' => 'عضویت و تمدید اعضا', 'version' => '2.0.0', 'dependencies' => ['members', 'membership_plans'], 'default_enabled' => false],
    ['key' => 'payments', 'name' => 'پرداخت‌ها', 'description' => 'ثبت وصول و مانده عضویت', 'version' => '2.0.0', 'dependencies' => ['memberships'], 'default_enabled' => false],
    ['key' => 'wallet', 'name' => 'کیف پول', 'description' => 'اعتبار داخلی و دفترکل مالی اعضا', 'version' => '1.0.0', 'dependencies' => ['members'], 'default_enabled' => false],
    ['key' => 'cafe', 'name' => 'کافه', 'description' => 'منو، سفارش و پرداخت از کیف پول', 'version' => '1.0.0', 'dependencies' => ['wallet', 'branches'], 'default_enabled' => false],
    ['key' => 'branding', 'name' => 'هویت بصری', 'description' => 'قالب و برند اختصاصی باشگاه', 'version' => '1.0.0', 'dependencies' => ['settings'], 'default_enabled' => true],
    ['key' => 'coaches', 'name' => 'مربیان', 'description' => 'مدیریت مربیان', 'version' => '2.0.0', 'dependencies' => ['gyms'], 'default_enabled' => false],
    ['key' => 'classes', 'name' => 'کلاس‌ها', 'description' => 'کلاس، برنامه و ثبت‌نام', 'version' => '2.0.0', 'dependencies' => ['coaches'], 'default_enabled' => false],
    ['key' => 'workouts', 'name' => 'برنامه تمرینی', 'description' => 'مدیریت تمرین‌ها', 'version' => '1.0.0', 'dependencies' => ['coaches'], 'default_enabled' => false],
    ['key' => 'wellness', 'name' => 'تغذیه و تمرین', 'description' => 'برنامه غذایی، دفتر خوراک و رکورد تمرین', 'version' => '1.0.0', 'dependencies' => ['members', 'coaches', 'attendance'], 'default_enabled' => false],
    ['key' => 'attendance', 'name' => 'حضور و غیاب', 'description' => 'پذیرش سریع و سوابق ورود', 'version' => '2.0.0', 'dependencies' => ['members', 'branches', 'memberships'], 'default_enabled' => false],
    ['key' => 'reports', 'name' => 'گزارش‌ها', 'description' => 'گزارش‌های عملیاتی و مالی', 'version' => '2.0.0', 'dependencies' => ['gyms'], 'default_enabled' => false],
    ['key' => 'activity', 'name' => 'فعالیت‌ها', 'description' => 'رویدادهای امن باشگاه', 'version' => '2.0.0', 'dependencies' => ['gyms'], 'default_enabled' => false],
    ['key' => 'search', 'name' => 'جست‌وجو', 'description' => 'جست‌وجوی عملیاتی سراسری', 'version' => '2.0.0', 'dependencies' => ['members'], 'default_enabled' => false],
    ['key' => 'notifications', 'name' => 'اعلان‌ها', 'description' => 'هشدارهای عملیاتی', 'version' => '2.0.0', 'dependencies' => ['memberships'], 'default_enabled' => false],
    ['key' => 'settings', 'name' => 'تنظیمات', 'description' => 'تنظیمات باشگاه', 'version' => '1.0.0', 'dependencies' => ['gyms'], 'default_enabled' => true],
]];
