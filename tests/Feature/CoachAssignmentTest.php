<?php

namespace Tests\Feature;

use App\Models\Coach;
use App\Models\CoachMemberAssignment;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class CoachAssignmentTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_manager_can_assign_coach_as_default(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'training']);

        $this->post(route('tenant.members.coaches.assign', $f['member']), [
            'coach_id' => $coach->id,
            'domain' => 'training',
            'is_default' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('coach_member_assignments', [
            'coach_id' => $coach->id,
            'member_id' => $f['member']->id,
            'domain' => 'training',
            'is_active' => 1,
            'is_default' => 1,
        ]);
    }

    public function test_manager_can_unassign_and_set_default(): void
    {
        $f = $this->fixture();
        $coachA = Coach::factory()->create(['coach_type' => 'both']);
        $coachB = Coach::factory()->create(['coach_type' => 'both']);
        $a = CoachMemberAssignment::query()->create(['coach_id' => $coachA->id, 'member_id' => $f['member']->id, 'domain' => 'training']);
        $b = CoachMemberAssignment::query()->create(['coach_id' => $coachB->id, 'member_id' => $f['member']->id, 'domain' => 'training']);

        $this->patch(route('tenant.members.coaches.default', $a))->assertRedirect();
        $this->assertSame(1, (int) $a->refresh()->is_default);
        $this->assertSame(0, (int) $b->refresh()->is_default);

        $this->delete(route('tenant.members.coaches.unassign', $a))->assertRedirect();
        $this->assertSame(0, (int) $a->refresh()->is_active);
        $this->assertSame(0, (int) $a->refresh()->is_default);
    }

    public function test_staff_cannot_assign_coach(): void
    {
        $f = $this->fixture('staff');
        $coach = Coach::factory()->create(['coach_type' => 'training']);

        $this->post(route('tenant.members.coaches.assign', $f['member']), [
            'coach_id' => $coach->id,
            'domain' => 'training',
        ])->assertForbidden();

        $this->assertDatabaseMissing('coach_member_assignments', [
            'coach_id' => $coach->id,
            'member_id' => $f['member']->id,
        ]);
    }

    public function test_cross_gym_assignment_actions_are_not_found(): void
    {
        $f = $this->fixture();
        // An assignment owned by another gym.
        [$gymB] = $this->createGymWithOwner();
        $this->enableModules($gymB, ['wellness']);
        $this->setGymContext($gymB);
        $branchB = $this->createBranch($gymB);
        $memberB = Member::factory()->create(['branch_id' => $branchB->id]);
        $coachB = Coach::factory()->create(['coach_type' => 'training']);
        $foreign = CoachMemberAssignment::query()->create(['coach_id' => $coachB->id, 'member_id' => $memberB->id, 'domain' => 'training']);

        $this->patch(route('tenant.members.coaches.default', $foreign))->assertNotFound();
        $this->delete(route('tenant.members.coaches.unassign', $foreign))->assertNotFound();
    }

    public function test_member_show_page_displays_assigned_coaches(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'training', 'first_name' => 'مربی', 'last_name' => 'ویژه']);
        CoachMemberAssignment::query()->create(['coach_id' => $coach->id, 'member_id' => $f['member']->id, 'domain' => 'training', 'is_default' => true]);

        $this->get(route('tenant.members.show', $f['member']))
            ->assertOk()
            ->assertSee('مربی ویژه')
            ->assertSee('مربیان منتسب');
    }

    /**
     * @return array{member: Member}
     */
    private function fixture(string $actingRole = 'manager'): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, ['wellness']);
        $this->setGymContext($gym);
        $branch = $this->createBranch($gym);
        $member = Member::factory()->create(['branch_id' => $branch->id]);

        $user = $owner;
        if ($actingRole !== 'owner') {
            $user = User::factory()->create();
            $this->attachUser($gym, $user, $actingRole);
        }

        $this->actAsUserInGym($user, $gym);

        return ['member' => $member];
    }
}
