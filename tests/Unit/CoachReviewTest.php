<?php

namespace Tests\Unit;

use App\Models\Coach;
use App\Models\CoachRequest;
use App\Models\Member;
use App\Models\User;
use App\Modules\Commercial\Application\CommercialWorkflows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class CoachReviewTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    private CommercialWorkflows $workflows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflows = app(CommercialWorkflows::class);
    }

    public function test_manager_can_review_any_request(): void
    {
        $f = $this->fixture();
        $req = CoachRequest::query()->create(['member_id' => $f['member']->id, 'domain' => 'training', 'type' => 'assign', 'status' => 'pending']);

        $this->assertTrue($this->workflows->canReviewCoachRequest($f['owner'], $req));
    }

    public function test_target_coach_can_review(): void
    {
        $f = $this->fixture();
        $req = CoachRequest::query()->create(['member_id' => $f['member']->id, 'coach_id' => $f['coach']->id, 'domain' => 'training', 'type' => 'assign', 'status' => 'pending']);

        $this->assertTrue($this->workflows->canReviewCoachRequest($f['coachUser'], $req));
    }

    public function test_default_coach_can_review_unaddressed_request(): void
    {
        $f = $this->fixture();
        $this->workflows->assignCoach($f['member'], $f['coach'], 'training', true, $f['request']);
        $req = CoachRequest::query()->create(['member_id' => $f['member']->id, 'domain' => 'training', 'type' => 'assign', 'status' => 'pending']);

        $this->assertTrue($this->workflows->canReviewCoachRequest($f['coachUser'], $req));
    }

    public function test_non_target_coach_cannot_review(): void
    {
        $f = $this->fixture();
        $other = Coach::factory()->create(['coach_type' => 'training']);
        $req = CoachRequest::query()->create(['member_id' => $f['member']->id, 'coach_id' => $other->id, 'domain' => 'training', 'type' => 'assign', 'status' => 'pending']);

        $this->assertFalse($this->workflows->canReviewCoachRequest($f['coachUser'], $req));
    }

    public function test_unaddressed_request_without_default_is_not_reviewable(): void
    {
        $f = $this->fixture();
        $req = CoachRequest::query()->create(['member_id' => $f['member']->id, 'domain' => 'training', 'type' => 'assign', 'status' => 'pending']);

        $this->assertFalse($this->workflows->canReviewCoachRequest($f['coachUser'], $req));
    }

    public function test_member_cannot_review(): void
    {
        $f = $this->fixture();
        $req = CoachRequest::query()->create(['member_id' => $f['member']->id, 'coach_id' => $f['coach']->id, 'domain' => 'training', 'type' => 'assign', 'status' => 'pending']);

        $this->assertFalse($this->workflows->canReviewCoachRequest($f['memberUser'], $req));
    }

    /**
     * @return array{gym: mixed, owner: User, member: Member, coach: Coach, coachUser: User, memberUser: User, request: Request}
     */
    private function fixture(): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->setGymContext($gym);
        $branch = $this->createBranch($gym);
        $member = Member::factory()->create(['branch_id' => $branch->id]);
        $coachUser = User::factory()->create();
        $coach = Coach::factory()->create(['user_id' => $coachUser->id, 'coach_type' => 'training']);
        $memberUser = User::factory()->create();
        $request = Request::create('/test', 'POST');
        $request->setUserResolver(fn () => $owner);

        return ['gym' => $gym, 'owner' => $owner, 'member' => $member, 'coach' => $coach, 'coachUser' => $coachUser, 'memberUser' => $memberUser, 'request' => $request];
    }
}
