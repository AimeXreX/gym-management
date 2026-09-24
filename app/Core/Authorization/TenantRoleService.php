<?php

namespace App\Core\Authorization;

use App\Core\Tenancy\GymContext;
use App\Models\User;

class TenantRoleService
{
    public function role(User $user): ?string
    {
        $gym = app(GymContext::class)->gym();

        if ($gym->owner_id === $user->id) {
            return 'owner';
        }

        return $user->gyms()
            ->whereKey($gym->id)
            ->wherePivot('status', 'active')
            ->first()?->pivot?->role;
    }

    public function isManager(User $user): bool
    {
        return in_array($this->role($user), ['owner', 'manager'], true);
    }
}
