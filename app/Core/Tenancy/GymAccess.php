<?php

namespace App\Core\Tenancy;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GymAccess
{
    /** @return Collection<int, Gym> */
    public function availableFor(User $user): Collection
    {
        return $user->gyms()
            ->wherePivot('status', 'active')
            ->where('gyms.status', 'active')
            ->orderBy('gyms.name')
            ->get();
    }

    public function findAvailable(User $user, int $gymId): ?Gym
    {
        return $user->gyms()
            ->wherePivot('status', 'active')
            ->where('gyms.status', 'active')
            ->find($gymId);
    }
}
