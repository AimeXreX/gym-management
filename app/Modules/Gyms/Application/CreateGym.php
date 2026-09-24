<?php

namespace App\Modules\Gyms\Application;

use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\GymContext;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\User;
use App\Modules\ModuleManagement\Application\UpdateGymEntitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateGym
{
    public function __construct(
        private readonly GymContext $context,
        private readonly UpdateGymEntitlements $entitlements,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(array $data, User $actor, Request $request): Gym
    {
        return DB::transaction(function () use ($data, $actor, $request): Gym {
            $owner = User::query()->firstOrCreate(
                ['email' => $data['owner_email']],
                ['name' => $data['owner_name'], 'password' => Str::password(32), 'locale' => 'fa'],
            );

            $gym = Gym::query()->create([
                'owner_id' => $owner->id,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'status' => $data['status'],
                'timezone' => $data['timezone'],
                'locale' => $data['locale'],
                'currency' => 'IRR',
            ]);

            $gym->users()->attach($owner->id, ['status' => 'active', 'joined_at' => now()]);

            try {
                $this->context->set($gym);
                Branch::query()->create([
                    'name' => __('ui.default_branch'),
                    'is_system_default' => true,
                    'status' => 'active',
                ]);
            } finally {
                $this->context->clear();
            }

            $changes = $this->entitlements->handle($gym, $data['modules'] ?? []);
            $this->audit->record('platform.gym.created', $request, $actor, $gym->id, $gym, [
                'owner_id' => $owner->id,
                'owner_created' => $owner->wasRecentlyCreated,
                'modules' => $changes['after'],
            ]);

            return $gym;
        });
    }
}
