<?php

namespace Tests\Unit;

use App\Core\Authorization\PermissionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_owner_allows_everything(): void
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->setGymContext($gym);

        $this->assertTrue(app(PermissionService::class)->allows($owner, 'anything.at.all'));
    }

    public function test_manager_allows_every_permission(): void
    {
        [$gym] = $this->createGymWithOwner();
        $manager = User::factory()->create();
        $this->attachUser($gym, $manager, 'manager');
        $this->setGymContext($gym);

        $this->assertTrue(app(PermissionService::class)->allows($manager, 'reports.view'));
    }

    public function test_staff_role_catalog_is_enforced(): void
    {
        [$gym] = $this->createGymWithOwner();
        $staff = User::factory()->create();
        $this->attachUser($gym, $staff, 'staff');
        $this->setGymContext($gym);

        $service = app(PermissionService::class);
        $this->assertTrue($service->allows($staff, 'attendance.checkin'));
        $this->assertFalse($service->allows($staff, 'payments.manage'));
    }

    public function test_custom_permissions_override_role_catalog(): void
    {
        [$gym] = $this->createGymWithOwner();
        $user = User::factory()->create();
        $this->attachUser($gym, $user, 'staff', ['permissions_json' => ['payments.manage']]);
        $this->setGymContext($gym);

        $service = app(PermissionService::class);
        $this->assertTrue($service->allows($user, 'payments.manage'));
        $this->assertFalse($service->allows($user, 'attendance.checkin'));
    }

    public function test_inactive_membership_is_denied(): void
    {
        [$gym] = $this->createGymWithOwner();
        $user = User::factory()->create();
        $this->attachUser($gym, $user, 'manager', ['status' => 'inactive']);
        $this->setGymContext($gym);

        $this->assertFalse(app(PermissionService::class)->allows($user, 'anything'));
    }
}
