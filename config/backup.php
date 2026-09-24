<?php
return [
    'path' => env('BACKUP_PATH', storage_path('app/backups')),
    'retention' => (int) env('BACKUP_RETENTION', 7),
    'password' => env('BACKUP_ENCRYPTION_PASSWORD'),
    'max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 30),
    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
];
