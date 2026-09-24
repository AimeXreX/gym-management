<?php

namespace Tests\Feature;

use App\Models\Coach;
use App\Models\CoachMemberAssignment;
use App\Models\CoachRequest;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class CoachReviewTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_target_coach_can_approve_their_request(): void
    {
        $f = $this->fixture();
        $req = CoachRequest::query()->create([
            'member_id' => $f['member']->id,
            'coach_id' => $f['coach']->id,
            'domain' => 'training',
            'type' => 'assign',
            'status' => 'pending',
        ]);

        $this->post(route('tenant.wellness.coach-requests.review', $req), ['approve' => 1])->assertRedirect();

        $this->assertSame('approved', $req->refresh()->status);
        $this->assertDatabaseHas('coach_member_assignments', [
            'coach_id' => $f['coach']->id,
            'member_id' => $f['member']->id,
            'domain' => 'training',
        ]);
    }

    public function test_target_coach_can_reject_with_reason(): void
    {
        $f = $this->fixture();
        $req = CoachRequest::query()->create([
            'member_id' => $f['member']->id,
            'coach_id' => $f['coach']->id,
            'domain' => 'training',
            'type' => 'program',
            'status' => 'pending',
        ]);

        $this->post(route('tenant.wellness.coach-requests.review', $req), [
            'approve' => 0,
            'review_note' => 'ظرفیت تکمیل است',
        ])->assertRedirect();

        $this->assertSame('rejected', $req->refresh()->status);
        $this->assertSame('ظرفیت تکمیل است', $req->review_note);
    }

    public function test_default_coach_can_approve_unaddressed_request(): void
    {
        $f = $this->fixture();
        CoachMemberAssignment::query()->create([
            'coach_id' => $f['coach']->id,
            'member_id' => $f['member']->id,
            'domain' => 'training',
            'is_active' => true,
            'is_default' => true,
        ]);
        $req = CoachRequest::query()->create([
            'member_id' => $f['member']->id,
            'domain' => 'training',
            'type' => 'assign',
            'status' => 'pending',
        ]);

        $this->post(route('tenant.wellness.coach-requests.review', $req), ['approve' => 1])->assertRedirect();

        $this->assertSame('approved', $req->refresh()->status);
        $this->assertDatabaseHas('coach_member_assignments', [
            'coach_id' => $f['coach']->id,
            'member_id' => $f['member']->id,
            'domain' => 'training',
            'is_default' => 1,
        ]);
    }

    public function test_non_target_coach_cannot_review(): void
    {
        $f = $this->fixture();
        $other = Coach::factory()->create(['coach_type' => 'training']);
        $req = CoachRequest::query()->create([
            'member_id' => $f['member']->id,
            'coach_id' => $other->id,
            'domain' => 'training',
            'type' => 'assign',
            'status' => 'pending',
        ]);

        $this->post(route('tenant.wellness.coach-requests.review', $req), ['approve' => 1])->assertForbidden();
    }

    public function test_member_cannot_review(): void
    {
        $f = $this->fixture();
        $req = CoachRequest::query()->create([
            'member_id' => $f['member']->id,
            'coach_id' => $f['coach']->id,
            'domain' => 'training',
            'type' => 'assign',
            'status' => 'pending',
        ]);

        $memberUser = User::factory()->create();
        $this->attachUser($f['gym'], $memberUser, 'member');
        $this->actAsUserInGym($memberUser, $f['gym']);

        $this->post(route('tenant.wellness.coach-requests.review', $req), ['approve' => 1])->assertForbidden();
    }

    public function test_coach_inbox_lists_only_reviewable_requests(): void
    {
        $f = $this->fixture();
        CoachRequest::query()->create([
            'member_id' => $f['member']->id,
            'coach_id' => $f['coach']->id,
            'domain' => 'training',
            'type' => 'assign',
            'status' => 'pending',
            'message' => 'پیام مخصوص مربی من',
        ]);
        $other = Coach::factory()->create(['coach_type' => 'training']);
        CoachRequest::query()->create([
            'member_id' => $f['member']->id,
            'coach_id' => $other->id,
            'domain' => 'training',
            'type' => 'assign',
            'status' => 'pending',
            'message' => 'پیام مربی دیگر',
        ]);

        $this->get(route('tenant.wellness.coach-requests.index'))
            ->assertOk()
            ->assertSee('پیام مخصوص مربی من')
            ->assertDontSee('پیام مربی دیگر');
    }

    /**
     * @return array{gym: mixed, member: Member, coach: Coach, coachUser: User}
     */
    private function fixture(): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, ['wellness']);
        $this->setGymContext($gym);
        $branch = $this->createBranch($gym);
        $member = Member::factory()->create(['branch_id' => $branch->id]);

        $coachUser = User::factory()->create();
        $this->attachUser($gym, $coachUser, 'coach');
        $coach = Coach::factory()->create(['user_id' => $coachUser->id, 'coach_type' => 'training']);

        $this->actAsUserInGym($coachUser, $gym);

        return ['gym' => $gym, 'owner' => $owner, 'member' => $member, 'coach' => $coach, 'coachUser' => $coachUser];
    }
}
