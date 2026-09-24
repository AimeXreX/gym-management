<?php

namespace Tests\Feature;

use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class MembershipWorkflowTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_manager_can_renew_a_member_with_an_active_plan(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships']);
        $manager = $this->managerFor($fixture['gym']);

        $this->actAsUserInGym($manager, $fixture['gym']);
        $this->from('/members')
            ->post("/members/{$fixture['member']->id}/renew", ['membership_plan_id' => $fixture['plan']->id])
            ->assertRedirect('/members');

        $membership = MemberMembership::query()->where('member_id', $fixture['member']->id)->first();

        $this->assertNotNull($membership);
        $this->assertSame($fixture['plan']->id, $membership->membership_plan_id);
        $this->assertSame('active', $membership->status);
        $this->assertSame('100.00', $membership->payable_amount);
        $this->assertSame($fixture['plan']->duration_days - 1, (int) $membership->starts_at->diffInDays($membership->ends_at));
    }

    public function test_staff_cannot_renew_a_member(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships']);
        $staff = $this->staffFor($fixture['gym']);

        $this->actAsUserInGym($staff, $fixture['gym']);
        $this->post("/members/{$fixture['member']->id}/renew", ['membership_plan_id' => $fixture['plan']->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('member_memberships', ['member_id' => $fixture['member']->id]);
    }

    public function test_renew_with_an_inactive_plan_is_rejected(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships']);
        $fixture['plan']->update(['is_active' => false]);
        $manager = $this->managerFor($fixture['gym']);

        $this->actAsUserInGym($manager, $fixture['gym']);
        $this->from('/members')
            ->post("/members/{$fixture['member']->id}/renew", ['membership_plan_id' => $fixture['plan']->id])
            ->assertSessionHasErrors('membership_plan_id');

        $this->assertDatabaseMissing('member_memberships', ['member_id' => $fixture['member']->id]);
    }

    public function test_manager_can_create_a_membership_plan(): void
    {
        $fixture = $this->createCommercialFixture(['membership_plans']);
        $manager = $this->managerFor($fixture['gym']);

        $this->actAsUserInGym($manager, $fixture['gym']);
        $this->from('/membership-plans')
            ->post('/membership-plans', [
                'name' => 'طرح ماهانه',
                'duration_days' => 30,
                'price' => 250,
                'currency' => 'IRR',
                'is_active' => 1,
            ])
            ->assertRedirect('/membership-plans');

        $plan = MembershipPlan::query()->where('name', 'طرح ماهانه')->first();

        $this->assertNotNull($plan);
        $this->assertSame($fixture['gym']->id, $plan->gym_id);
        $this->assertSame('250.00', (string) $plan->price);
    }

    public function test_membership_plans_page_requires_the_module(): void
    {
        $fixture = $this->createCommercialFixture(['membership_plans']);

        $this->actAsUserInGym($fixture['owner'], $fixture['gym']);
        $this->get('/membership-plans')->assertOk();
    }

    public function test_membership_plans_page_is_hidden_when_module_disabled(): void
    {
        $fixture = $this->createCommercialFixture([]);

        $this->actAsUserInGym($fixture['owner'], $fixture['gym']);
        $this->get('/membership-plans')->assertNotFound();
    }

    public function test_member_show_displays_the_active_membership(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships']);
        $manager = $this->managerFor($fixture['gym']);

        $this->actAsUserInGym($manager, $fixture['gym']);
        $this->from('/members')
            ->post("/members/{$fixture['member']->id}/renew", ['membership_plan_id' => $fixture['plan']->id])
            ->assertRedirect('/members');

        $this->get("/members/{$fixture['member']->id}")
            ->assertOk()
            ->assertSee($fixture['plan']->name);
    }

    private function managerFor($gym): User
    {
        $manager = User::factory()->create();
        $this->attachUser($gym, $manager, 'manager');

        return $manager;
    }

    private function staffFor($gym): User
    {
        $staff = User::factory()->create();
        $this->attachUser($gym, $staff, 'staff');

        return $staff;
    }
}
