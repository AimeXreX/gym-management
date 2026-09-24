<?php

namespace App\Core\Tenancy;

use Illuminate\Contracts\Session\Session;

class GymSelection
{
    private const SESSION_KEY = 'current_gym_id';

    public function __construct(private readonly Session $session) {}

    public function id(): ?int
    {
        $value = $this->session->get(self::SESSION_KEY);

        return is_numeric($value) ? (int) $value : null;
    }

    public function select(int $gymId): void
    {
        $this->session->put(self::SESSION_KEY, $gymId);
    }

    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
