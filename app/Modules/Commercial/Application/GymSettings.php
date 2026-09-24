<?php

namespace App\Modules\Commercial\Application;

use App\Core\Tenancy\GymContext;

class GymSettings
{
    public function __construct(private GymContext $context) {}

    public function all(): array
    {
        return array_merge([
            'expiry_warning_days' => 14,
            'membership_code_prefix' => 'GYM',
            'duplicate_checkin_minutes' => 15,
            'qr_checkin_enabled' => true,
            'expired_checkin_policy' => 'block',
        ], $this->context->gym()->settings_json ?? []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }
}
