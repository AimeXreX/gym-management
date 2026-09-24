<?php

namespace App\Core\Authorization;

use App\Core\Tenancy\GymContext;
use App\Models\User;

class PermissionService
{
    private const ROLE_CATALOG = [
        'manager' => ['*'],
        'staff' => ['dashboard.view', 'members.view', 'attendance.view', 'attendance.checkin', 'classes.view', 'cafe.sell', 'wallet.view'],
        'accountant' => ['dashboard.view', 'members.view', 'payments.view', 'payments.manage', 'reports.view', 'wallet.view', 'wallet.charge'],
        'coach' => ['dashboard.view', 'members.view', 'classes.view', 'attendance.view'],
        'nutrition_coach' => ['dashboard.view', 'members.view'],
        'member' => ['dashboard.view'],
    ];

    public function allows(User $user, string $permission): bool
    {
        $gym = app(GymContext::class)->gym();
        if ($gym->owner_id === $user->id) {
            return true;
        }
        $membership = $user->gyms()->whereKey($gym->id)->wherePivot('status', 'active')->first()?->pivot;
        if (! $membership) {
            return false;
        }
        // The pivot cast already decodes JSON into an array; accept both shapes.
        $raw = $membership->permissions_json;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        $permissions = is_array($raw) ? $raw : (self::ROLE_CATALOG[$membership->role ?? 'staff'] ?? []);

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
