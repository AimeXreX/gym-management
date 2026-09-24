<?php

namespace Tests\Unit;

use App\Models\Coach;
use App\Models\CoachMemberAssignment;
use App\Models\Member;
use App\Modules\Commercial\Application\CommercialWorkflows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class CoachAssignmentTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    private CommercialWorkflows $workflows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflows = app(CommercialWorkflows::class);
    }

    public function test_assign_creates_an_active_assignment(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'training']);

        $assignment = $this->workflows->assignCoach($f['member'], $coach, 'training', false, $f['request']);

        $this->assertTrue($assignment->is_active);
        $this->assertFalse($assignment->is_default);
        $this->assertSame('training', $assignment->domain);
        $this->assertSame($coach->id, $assignment->coach_id);
        $this->assertSame($f['member']->id, $assignment->member_id);
        $this->assertSame($f['gym']->id, $assignment->gym_id);
    }

    public function test_assign_as_default_clears_other_defaults_in_the_same_domain(): void
    {
        $f = $this->fixture();
        $coachA = Coach::factory()->create(['coach_type' => 'both']);
        $coachB = Coach::factory()->create(['coach_type' => 'both']);
        $this->workflows->assignCoach($f['member'], $coachA, 'training', true, $f['request']);

        $this->workflows->assignCoach($f['member'], $coachB, 'training', true, $f['request']);

        $defaults = CoachMemberAssignment::query()
            ->where('member_id', $f['member']->id)
            ->where('domain', 'training')
            ->where('is_default', true)
            ->count();
        $this->assertSame(1, $defaults);
    }

    public function test_assign_reactivates_a_deactivated_assignment(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'training']);
        $assignment = $this->workflows->assignCoach($f['member'], $coach, 'training', false, $f['request']);
        $this->workflows->unassignCoach($assignment, $f['request']);

        $reactivated = $this->workflows->assignCoach($f['member'], $coach, 'training', false, $f['request']);

        $this->assertSame($assignment->id, $reactivated->id);
        $this->assertTrue($reactivated->refresh()->is_active);
    }

    public function test_assign_rejects_invalid_domain(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'both']);

        $this->expectException(ValidationException::class);
        $this->workflows->assignCoach($f['member'], $coach, 'yoga', false, $f['request']);
    }

    public function test_assign_rejects_inactive_coach(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'training', 'status' => 'inactive']);

        $this->expectException(ValidationException::class);
        $this->workflows->assignCoach($f['member'], $coach, 'training', false, $f['request']);
    }

    public function test_assign_rejects_coach_type_mismatch(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'nutrition']);

        $this->expectException(ValidationException::class);
        $this->workflows->assignCoach($f['member'], $coach, 'training', false, $f['request']);
    }

    public function test_both_type_coach_can_be_assigned_to_both_domains(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'both']);

        $this->workflows->assignCoach($f['member'], $coach, 'training', false, $f['request']);
        $this->workflows->assignCoach($f['member'], $coach, 'nutrition', false, $f['request']);

        $this->assertSame(2, CoachMemberAssignment::query()->where('coach_id', $coach->id)->where('member_id', $f['member']->id)->count());
    }

    public function test_unassign_deactivates_and_clears_default(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'training']);
        $assignment = $this->workflows->assignCoach($f['member'], $coach, 'training', true, $f['request']);

        $this->workflows->unassignCoach($assignment, $f['request']);

        $this->assertFalse($assignment->refresh()->is_active);
        $this->assertFalse($assignment->refresh()->is_default);
    }

    public function test_set_default_marks_single_default_per_domain(): void
    {
        $f = $this->fixture();
        $coachA = Coach::factory()->create(['coach_type' => 'both']);
        $coachB = Coach::factory()->create(['coach_type' => 'both']);
        $a = $this->workflows->assignCoach($f['member'], $coachA, 'training', false, $f['request']);
        $b = $this->workflows->assignCoach($f['member'], $coachB, 'training', false, $f['request']);

        $this->workflows->setDefaultCoach($a, $f['request']);

        $this->assertTrue($a->refresh()->is_default);
        $this->assertFalse($b->refresh()->is_default);
    }

    public function test_set_default_rejects_inactive_assignment(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'training']);
        $assignment = $this->workflows->assignCoach($f['member'], $coach, 'training', false, $f['request']);
        $this->workflows->unassignCoach($assignment, $f['request']);

        $this->expectException(ValidationException::class);
        $this->workflows->setDefaultCoach($assignment->refresh(), $f['request']);
    }

    /**
     * @return array{gym: mixed, member: Member, request: Request}
     */
    private function fixture(): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->setGymContext($gym);
        $branch = $this->createBranch($gym);
        $member = Member::factory()->create(['branch_id' => $branch->id]);
        $request = Request::create('/test', 'POST');
        $request->setUserResolver(fn () => $owner);

        return ['gym' => $gym, 'member' => $member, 'request' => $request];
    }
}
