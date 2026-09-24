<?php

namespace App\Modules\Commercial\Application;

use App\Models\ClassSchedule;
use App\Models\ClassSession;
use Carbon\Carbon;

class GenerateClassSessions
{
    public function handle(int $days = 30): int
    {
        $created = 0;
        ClassSchedule::with('gymClass')->where('status', 'active')->whereHas('gymClass', fn ($q) => $q->where('status', 'active'))->each(function ($schedule) use ($days, &$created) {
            for ($date = today(); $date->lte(today()->addDays($days)); $date->addDay()) {
                if ($date->dayOfWeek !== $schedule->weekday) {
                    continue;
                }
                $start = Carbon::parse($date->format('Y-m-d').' '.$schedule->starts_at);
                $session = ClassSession::firstOrCreate(['class_schedule_id' => $schedule->id, 'starts_at' => $start], ['gym_class_id' => $schedule->gym_class_id, 'ends_at' => $start->copy()->addMinutes($schedule->gymClass->duration_minutes), 'status' => 'scheduled']);
                if ($session->wasRecentlyCreated) {
                    $created++;
                }
            }
        });

        return $created;
    }
}
