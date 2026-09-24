<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Exceptions\MissingGymContext;
use App\Models\Gym;

class GymContext
{
    private ?Gym $gym = null;

    public function set(Gym $gym): void
    {
        $this->gym = $gym;
    }

    public function clear(): void
    {
        $this->gym = null;
    }

    public function has(): bool
    {
        return $this->gym !== null;
    }

    public function gym(): Gym
    {
        return $this->gym ?? throw MissingGymContext::make();
    }

    public function id(): int
    {
        return $this->gym()->getKey();
    }
}
