<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_staff_cannot_access_manager_only_settings(): void
    {
        [$gym] = $this->createGymWithOwner();
        $staff = User::factory()->create();
        $this->attachUser($gym, $staff, 'staff');
        $this->enableModules($gym, ['settings']);

        $this->actAsUserInGym($staff, $gym);

        $this->get('/settings')->assertForbidden();
    }

    public function test_manager_can_access_settings(): void
    {
        [$gym] = $this->createGymWithOwner();
        $manager = User::factory()->create();
        $this->attachUser($gym, $manager, 'manager');
        $this->enableModules($gym, ['settings']);

        $this->actAsUserInGym($manager, $gym);

        $this->get('/settings')->assertOk();
    }

    public function test_staff_can_view_members_but_cannot_create_them(): void
    {
        [$gym] = $this->createGymWithOwner();
        $staff = User::factory()->create();
        $this->attachUser($gym, $staff, 'staff');
        $this->enableModules($gym, ['members']);

        $this->actAsUserInGym($staff, $gym);

        $this->get('/members')->assertOk();
        $this->post('/members', [])->assertForbidden();
    }

    public function test_permission_middleware_respects_custom_permissions(): void
    {
        [$gym] = $this->createGymWithOwner();
        // A manager whose custom permissions do not include reports.
        $manager = User::factory()->create();
        $this->attachUser($gym, $manager, 'manager', ['permissions_json' => ['dashboard.view']]);
        $this->enableModules($gym, ['dashboard', 'reports']);

        $this->actAsUserInGym($manager, $gym);

        $this->get('/dashboard')->assertOk();
        $this->get('/reports')->assertForbidden();
    }

    public function test_platform_admin_can_access_platform(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin);

        $this->get('/platform')->assertOk();
    }

    public function test_non_admin_cannot_access_platform(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/platform')->assertForbidden();
    }
}
