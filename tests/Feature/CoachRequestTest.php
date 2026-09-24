<?php

namespace Tests\Feature;

use App\Models\Coach;
use App\Models\CoachRequest;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class CoachRequestTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_member_can_submit_request(): void
    {
        $f = $this->fixture('member');
        $f['member']->update(['user_id' => $f['user']->id]);

        $this->post(route('tenant.wellness.coach-requests.store', $f['member']), [
            'type' => 'assign',
            'domain' => 'training',
            'message' => 'سلام',
        ])->assertRedirect();

        $this->assertDatabaseHas('coach_requests', [
            'member_id' => $f['member']->id,
            'type' => 'assign',
            'status' => 'pending',
        ]);
    }

    public function test_manager_can_approve_assign_request(): void
    {
        $f = $this->fixture('manager');
        $coach = Coach::factory()->create(['coach_type' => 'training']);
        $req = CoachRequest::query()->create([
            'member_id' => $f['member']->id,
            'coach_id' => $coach->id,
            'domain' => 'training',
            'type' => 'assign',
            'status' => 'pending',
        ]);

        $this->post(route('tenant.wellness.coach-requests.review', $req), ['approve' => 1])->assertRedirect();

        $this->assertDatabaseHas('coach_requests', ['id' => $req->id, 'status' => 'approved']);
        $this->assertDatabaseHas('coach_member_assignments', [
            'coach_id' => $coach->id,
            'member_id' => $f['member']->id,
            'domain' => 'training',
        ]);
    }

    public function test_manager_can_reject_request_with_reason(): void
    {
        $f = $this->fixture('manager');
        $req = CoachRequest::query()->create([
            'member_id' => $f['member']->id,
            'domain' => 'training',
            'type' => 'program',
            'status' => 'pending',
        ]);

        $this->post(route('tenant.wellness.coach-requests.review', $req), [
            'approve' => 0,
            'review_note' => 'ظرفیت تکمیل است',
        ])->assertRedirect();

        $this->assertDatabaseHas('coach_requests', ['id' => $req->id, 'status' => 'rejected']);
    }

    public function test_staff_cannot_review_request(): void
    {
        $f = $this->fixture('staff');
        $req = CoachRequest::query()->create([
            'member_id' => $f['member']->id,
            'domain' => 'training',
            'type' => 'assign',
            'status' => 'pending',
        ]);

        $this->post(route('tenant.wellness.coach-requests.review', $req), ['approve' => 1])->assertForbidden();
    }

    public function test_member_cannot_submit_request_for_another_member(): void
    {
        $f = $this->fixture('member');
        $f['member']->update(['user_id' => $f['user']->id]);
        $other = Member::factory()->create(['branch_id' => $f['branch']->id]);

        $this->post(route('tenant.wellness.coach-requests.store', $other), [
            'type' => 'assign',
            'domain' => 'training',
        ])->assertForbidden();
    }

    /**
     * @return array{gym: mixed, member: Member, user: User, branch: mixed}
     */
    private function fixture(string $role): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, ['wellness']);
        $this->setGymContext($gym);
        $branch = $this->createBranch($gym);
        $member = Member::factory()->create(['branch_id' => $branch->id]);

        $user = $owner;
        if ($role !== 'owner') {
            $user = User::factory()->create();
            $this->attachUser($gym, $user, $role);
        }
        $this->actAsUserInGym($user, $gym);

        return ['gym' => $gym, 'member' => $member, 'user' => $user, 'branch' => $branch];
    }
}
