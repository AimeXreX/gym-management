<?php

namespace App\Core\Authorization\Http\Middleware;

use App\Core\Tenancy\GymContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGymManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $gym = app(GymContext::class)->gym();
        $user = $request->user();
        $allowed = $gym->owner_id === $user->id || $user->gyms()->whereKey($gym->id)->wherePivotIn('role', ['owner', 'manager'])->exists();
        abort_unless($allowed, 403);

        return $next($request);
    }
}
