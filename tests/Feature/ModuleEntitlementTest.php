<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class ModuleEntitlementTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_disabled_module_route_returns_not_found(): void
    {
        [$gym, $owner] = $this->createGymWithOwner();
        // The 'members' module is intentionally not enabled for this gym.
        $this->enableModules($gym, ['gyms', 'branches']);

        $this->actAsUserInGym($owner, $gym);

        $this->get('/members')->assertNotFound();
    }

    public function test_enabled_module_route_returns_ok(): void
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, ['members']);

        $this->actAsUserInGym($owner, $gym);

        $this->get('/members')->assertOk();
    }

    public function test_dashboard_module_is_required(): void
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, ['dashboard']);

        $this->actAsUserInGym($owner, $gym);

        $this->get('/dashboard')->assertOk();
    }

    public function test_disabling_a_module_hides_its_routes(): void
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, ['dashboard']);

        $this->actAsUserInGym($owner, $gym);

        $this->get('/reports')->assertNotFound();
    }
}
