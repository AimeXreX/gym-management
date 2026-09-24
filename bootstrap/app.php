<?php

use App\Core\Authorization\Http\Middleware\EnsureGymManager;
use App\Core\Authorization\Http\Middleware\EnsurePermission;
use App\Core\Authorization\Http\Middleware\EnsureTenantAreaAccess;
use App\Core\Authorization\Http\Middleware\EnsurePlatformAdmin;
use App\Core\Modules\Http\Middleware\EnsureModuleEnabled;
use App\Core\Tenancy\Http\Middleware\ResolveGymContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', SecurityHeaders::class);
        $middleware->alias([
            'gym.context' => ResolveGymContext::class,
            'module' => EnsureModuleEnabled::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'gym.manage' => EnsureGymManager::class,
            'permission' => EnsurePermission::class,
            'tenant.area' => EnsureTenantAreaAccess::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveGymContext::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('tenant.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
