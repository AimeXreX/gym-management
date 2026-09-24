<?php

namespace App\Modules\Gyms\Application;

use App\Core\Audit\AuditLogger;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateGym
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Gym $gym, array $data, User $actor, Request $request): Gym
    {
        return DB::transaction(function () use ($gym, $data, $actor, $request): Gym {
            $before = $gym->only(['name', 'slug', 'status', 'timezone', 'locale']);
            $oldStatus = $gym->status;
            $gym->update($data);

            $event = $oldStatus !== $gym->status
                ? ($gym->status === 'active' ? 'platform.gym.activated' : 'platform.gym.deactivated')
                : 'platform.gym.updated';

            $this->audit->record($event, $request, $actor, $gym->id, $gym, [
                'before' => $before,
                'after' => $gym->only(array_keys($before)),
            ]);

            return $gym;
        });
    }
}
