<?php

namespace App\Core\Authorization\Http\Middleware;

use App\Core\Authorization\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless($request->user() && $this->permissions->allows($request->user(), $permission), 403);

        return $next($request);
    }
}
