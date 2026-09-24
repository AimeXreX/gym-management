<?php

use App\Core\Tenancy\GymContext;
use App\Models\Gym;
use App\Modules\Commercial\Application\GenerateClassSessions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\File;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('classes:generate-sessions {--days=30}', function () {
    $total = 0;
    Gym::query()->where('status', 'active')->each(function (Gym $gym) use (&$total) {
        app(GymContext::class)->set($gym);
        $total += app(GenerateClassSessions::class)->handle((int) $this->option('days'));
    });
    $this->info("Generated {$total} class sessions.");
})->purpose('Generate missing weekly class occurrences idempotently');

Artisan::command('monitor:heartbeat', function () { File::put(storage_path('framework/scheduler-heartbeat'), now()->toIso8601String()); $this->info('Heartbeat updated.'); });

Schedule::command('monitor:heartbeat')->everyMinute()->withoutOverlapping();
Schedule::command('classes:generate-sessions --days=30')->dailyAt('01:15')->withoutOverlapping();
Schedule::command('app:backup')->dailyAt('01:45')->withoutOverlapping()->onOneServer();
