<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_user_only_sees_members_of_the_selected_gym(): void
    {
        [$gymA, $ownerA] = $this->createGymWithOwner();
        [$gymB, $ownerB] = $this->createGymWithOwner();
        $this->enableModules($gymA, ['members']);
        $this->enableModules($gymB, ['members']);

        $this->setGymContext($gymA);
        $branchA = $this->createBranch($gymA);
        Member::factory()->create(['branch_id' => $branchA->id, 'membership_code' => 'GYM-A-001', 'first_name' => 'علی']);

        $this->setGymContext($gymB);
        $branchB = $this->createBranch($gymB);
        Member::factory()->create(['branch_id' => $branchB->id, 'membership_code' => 'GYM-B-001', 'first_name' => 'رضا']);

        $this->actAsUserInGym($ownerA, $gymA);

        $this->get('/members')
            ->assertOk()
            ->assertSee('GYM-A-001')
            ->assertDontSee('GYM-B-001');
    }

    public function test_cross_tenant_member_show_is_not_found(): void
    {
        [$gymA, $ownerA] = $this->createGymWithOwner();
        [$gymB, $ownerB] = $this->createGymWithOwner();
        $this->enableModules($gymA, ['members']);
        $this->enableModules($gymB, ['members']);

        $this->setGymContext($gymA);
        $branchA = $this->createBranch($gymA);
        $memberA = Member::factory()->create(['branch_id' => $branchA->id]);

        // Owner of gym B tries to open gym A's member record.
        $this->actAsUserInGym($ownerB, $gymB);

        $this->get("/members/{$memberA->id}")->assertNotFound();
    }

    public function test_owner_of_another_gym_sees_own_member(): void
    {
        [$gymA, $ownerA] = $this->createGymWithOwner();
        [$gymB, $ownerB] = $this->createGymWithOwner();
        $this->enableModules($gymA, ['members']);
        $this->enableModules($gymB, ['members']);

        $this->setGymContext($gymA);
        $branchA = $this->createBranch($gymA);
        $memberA = Member::factory()->create(['branch_id' => $branchA->id]);

        $this->setGymContext($gymB);
        $branchB = $this->createBranch($gymB);
        $memberB = Member::factory()->create(['branch_id' => $branchB->id]);

        $this->actAsUserInGym($ownerB, $gymB);

        $this->get("/members/{$memberB->id}")->assertOk();
        $this->get("/members/{$memberA->id}")->assertNotFound();
    }
}
