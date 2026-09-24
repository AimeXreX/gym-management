<?php

namespace Tests\Unit;

use App\Core\Authorization\TenantRoleService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class TenantRoleServiceTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_owner_has_owner_role(): void
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->setGymContext($gym);

        $this->assertSame('owner', app(TenantRoleService::class)->role($owner));
    }

    public function test_pivot_role_is_returned(): void
    {
        [$gym] = $this->createGymWithOwner();
        $user = User::factory()->create();
        $this->attachUser($gym, $user, 'accountant');
        $this->setGymContext($gym);

        $this->assertSame('accountant', app(TenantRoleService::class)->role($user));
    }

    public function test_inactive_pivot_has_no_role(): void
    {
        [$gym] = $this->createGymWithOwner();
        $user = User::factory()->create();
        $this->attachUser($gym, $user, 'manager', ['status' => 'inactive']);
        $this->setGymContext($gym);

        $this->assertNull(app(TenantRoleService::class)->role($user));
    }

    public function test_user_without_membership_has_no_role(): void
    {
        [$gym] = $this->createGymWithOwner();
        $this->setGymContext($gym);

        $this->assertNull(app(TenantRoleService::class)->role(User::factory()->create()));
    }
}
