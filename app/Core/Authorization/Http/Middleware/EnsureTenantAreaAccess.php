<?php

namespace App\Core\Authorization\Http\Middleware;

use App\Core\Authorization\TenantRoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAreaAccess
{
    private const PREFIXES = [
        'owner' => ['tenant.dashboard', 'tenant.members.', 'tenant.plans.', 'tenant.payments.', 'tenant.attendance.', 'tenant.branches.', 'tenant.coaches.', 'tenant.classes.', 'tenant.reports.', 'tenant.activity.', 'tenant.search', 'tenant.settings', 'tenant.wallets.', 'tenant.cafe.', 'tenant.branding.', 'tenant.wellness.', 'tenant.notifications.'],
        'manager' => ['tenant.dashboard', 'tenant.members.', 'tenant.plans.', 'tenant.payments.', 'tenant.attendance.', 'tenant.branches.', 'tenant.coaches.', 'tenant.classes.', 'tenant.reports.', 'tenant.activity.', 'tenant.search', 'tenant.settings', 'tenant.wallets.', 'tenant.cafe.', 'tenant.branding.', 'tenant.wellness.', 'tenant.notifications.'],
        'staff' => ['tenant.dashboard', 'tenant.members.', 'tenant.attendance.', 'tenant.classes.', 'tenant.coaches.', 'tenant.search', 'tenant.notifications.'],
        'accountant' => ['tenant.dashboard', 'tenant.members.index', 'tenant.members.show', 'tenant.payments.', 'tenant.reports.', 'tenant.wallets.', 'tenant.notifications.'],
        'coach' => ['tenant.dashboard', 'tenant.wellness.', 'tenant.notifications.'],
        'nutrition_coach' => ['tenant.dashboard', 'tenant.wellness.', 'tenant.notifications.'],
        'member' => ['tenant.dashboard', 'tenant.wellness.', 'tenant.notifications.'],
    ];

    public function __construct(private readonly TenantRoleService $roles) {}

    public function handle(Request $request, Closure $next): Response
    {
        $role = $this->roles->role($request->user());
        $name = (string) $request->route()?->getName();
        $allowed = collect(self::PREFIXES[$role] ?? [])->contains(
            fn (string $prefix) => $name === $prefix || str_starts_with($name, $prefix),
        );

        abort_unless($allowed, 403);

        return $next($request);
    }
}
