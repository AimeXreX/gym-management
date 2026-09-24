<?php

namespace App\Core\Tenancy\Http\Middleware;

use App\Core\Tenancy\GymContext;
use App\Core\Tenancy\GymContextResolver;
use App\Core\Tenancy\GymResolution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveGymContext
{
    public function __construct(private readonly GymContextResolver $resolver, private readonly GymContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        return match ($this->resolver->resolve($user)) {
            GymResolution::Resolved => $this->continueInGymTimezone($request, $next),
            GymResolution::NoGyms => redirect()->route('account.no-gym'),
            GymResolution::SelectionRequired,
            GymResolution::InvalidSelection => redirect()->route('account.gyms.select')
                ->with('warning', __('ui.gym_selection_required')),
        };
    }

    private function continueInGymTimezone(Request $request, Closure $next): Response
    {
        config(['app.timezone' => $this->context->gym()->timezone]);
        date_default_timezone_set($this->context->gym()->timezone);

        return $next($request);
    }
}
