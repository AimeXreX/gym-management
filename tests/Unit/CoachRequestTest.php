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

class CoachRequestTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    private CommercialWorkflows $workflows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflows = app(CommercialWorkflows::class);
    }

    public function test_request_creates_a_pending_request(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'training']);

        $req = $this->workflows->requestCoach($f['member'], $coach, 'training', 'assign', 'سلام', $f['request']);

        $this->assertSame('pending', $req->status);
        $this->assertSame($coach->id, $req->coach_id);
        $this->assertSame('assign', $req->type);
        $this->assertSame('training', $req->domain);
    }

    public function test_request_rejects_invalid_domain(): void
    {
        $f = $this->fixture();

        $this->expectException(ValidationException::class);
        $this->workflows->requestCoach($f['member'], null, 'yoga', 'assign', null, $f['request']);
    }

    public function test_request_rejects_coach_type_mismatch(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'nutrition']);

        $this->expectException(ValidationException::class);
        $this->workflows->requestCoach($f['member'], $coach, 'training', 'assign', null, $f['request']);
    }

    public function test_duplicate_pending_request_is_rejected(): void
    {
        $f = $this->fixture();
        $this->workflows->requestCoach($f['member'], null, 'training', 'assign', null, $f['request']);

        $this->expectException(ValidationException::class);
        $this->workflows->requestCoach($f['member'], null, 'training', 'assign', null, $f['request']);
    }

    public function test_approve_assign_creates_assignment_with_default_coach(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'training']);
        $this->workflows->assignCoach($f['member'], $coach, 'training', true, $f['request']);
        $req = $this->workflows->requestCoach($f['member'], null, 'training', 'assign', null, $f['request']);

        $this->workflows->reviewCoachRequest($req, true, null, $f['request']);

        $this->assertSame('approved', $req->refresh()->status);
        $this->assertDatabaseHas('coach_member_assignments', [
            'coach_id' => $coach->id,
            'member_id' => $f['member']->id,
            'domain' => 'training',
            'is_active' => 1,
            'is_default' => 1,
        ]);
    }

    public function test_approve_program_does_not_create_assignment(): void
    {
        $f = $this->fixture();
        $req = $this->workflows->requestCoach($f['member'], null, 'training', 'program', null, $f['request']);

        $this->workflows->reviewCoachRequest($req, true, null, $f['request']);

        $this->assertSame('approved', $req->refresh()->status);
        $this->assertSame(0, CoachMemberAssignment::query()->count());
    }

    public function test_reject_requires_reason(): void
    {
        $f = $this->fixture();
        $req = $this->workflows->requestCoach($f['member'], null, 'training', 'assign', null, $f['request']);

        $this->expectException(ValidationException::class);
        $this->workflows->reviewCoachRequest($req, false, null, $f['request']);
    }

    public function test_reviewing_an_already_reviewed_request_is_rejected(): void
    {
        $f = $this->fixture();
        $req = $this->workflows->requestCoach($f['member'], null, 'training', 'program', null, $f['request']);
        $this->workflows->reviewCoachRequest($req, true, null, $f['request']);

        $this->expectException(ValidationException::class);
        $this->workflows->reviewCoachRequest($req->refresh(), true, null, $f['request']);
    }

    public function test_approve_assign_without_default_coach_keeps_request_pending(): void
    {
        $f = $this->fixture();
        $req = $this->workflows->requestCoach($f['member'], null, 'training', 'assign', null, $f['request']);

        try {
            $this->workflows->reviewCoachRequest($req, true, null, $f['request']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame('pending', $req->refresh()->status);
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
