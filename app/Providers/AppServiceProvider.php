<?php

namespace App\Providers;

use App\Core\Authorization\PermissionService;
use App\Core\Authorization\TenantRoleService;
use App\Core\Modules\ModuleManager;
use App\Core\Modules\ModuleRegistry;
use App\Core\Tenancy\GymContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(GymContext::class);
        $this->app->singleton(ModuleRegistry::class);
        $this->app->scoped(ModuleManager::class);
        $this->app->scoped(PermissionService::class);
        $this->app->scoped(TenantRoleService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Blade::if('module', fn (string $key): bool => app(ModuleManager::class)->enabled($key));
        Blade::if('permission', fn (string $key): bool => auth()->check() && app(PermissionService::class)->allows(auth()->user(), $key));
        Gate::define('access-platform', fn ($user): bool => $user->is_platform_admin === true);
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
    }
}
