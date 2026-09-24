<?php

namespace App\Core\Tenancy;

use App\Models\User;

class GymContextResolver
{
    public function __construct(
        private readonly GymAccess $access,
        private readonly GymSelection $selection,
        private readonly GymContext $context,
    ) {}

    public function resolve(User $user): GymResolution
    {
        $selectedId = $this->selection->id();

        if ($selectedId === null) {
            return $this->access->availableFor($user)->isEmpty()
                ? GymResolution::NoGyms
                : GymResolution::SelectionRequired;
        }

        $gym = $this->access->findAvailable($user, $selectedId);

        if ($gym === null) {
            $this->selection->forget();
            $this->context->clear();

            return $this->access->availableFor($user)->isEmpty()
                ? GymResolution::NoGyms
                : GymResolution::InvalidSelection;
        }

        $this->context->set($gym);

        return GymResolution::Resolved;
    }
}
