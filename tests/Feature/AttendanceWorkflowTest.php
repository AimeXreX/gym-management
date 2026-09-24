<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\MemberMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class AttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_staff_can_check_in_a_member_with_an_active_membership(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'attendance']);
        $this->renewAsManager($fixture);
        $staff = $this->staffFor($fixture['gym']);
        $this->actAsUserInGym($staff, $fixture['gym']);

        $this->from('/attendance/check-in')
            ->post("/attendance/check-in/{$fixture['member']->id}", ['method' => 'manual'])
            ->assertRedirect('/attendance/check-in');

        $this->assertDatabaseHas('attendances', [
            'member_id' => $fixture['member']->id,
            'method' => 'manual',
        ]);

        $fixture['member']->refresh();
        $this->assertNotNull($fixture['member']->last_attended_at);
    }

    public function test_check_in_without_an_active_membership_is_rejected(): void
    {
        // A member with no membership at all.
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'attendance']);
        $staff = $this->staffFor($fixture['gym']);
        $this->actAsUserInGym($staff, $fixture['gym']);

        $this->from('/attendance/check-in')
            ->post("/attendance/check-in/{$fixture['member']->id}", ['method' => 'manual'])
            ->assertSessionHasErrors('member');

        $this->assertDatabaseMissing('attendances', ['member_id' => $fixture['member']->id]);
    }

    public function test_duplicate_check_in_within_window_is_rejected(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'attendance']);
        $this->renewAsManager($fixture);
        $staff = $this->staffFor($fixture['gym']);
        $this->actAsUserInGym($staff, $fixture['gym']);

        $this->from('/attendance/check-in')
            ->post("/attendance/check-in/{$fixture['member']->id}", ['method' => 'manual'])
            ->assertRedirect('/attendance/check-in');

        $this->from('/attendance/check-in')
            ->post("/attendance/check-in/{$fixture['member']->id}", ['method' => 'manual'])
            ->assertSessionHasErrors('member');
    }

    public function test_check_in_requires_the_checkin_permission(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'attendance']);
        $this->renewAsManager($fixture);

        // Staff role but without the attendance.checkin permission.
        $staff = User::factory()->create();
        $this->attachUser($fixture['gym'], $staff, 'staff', ['permissions_json' => ['attendance.view']]);
        $this->actAsUserInGym($staff, $fixture['gym']);

        $this->post("/attendance/check-in/{$fixture['member']->id}", ['method' => 'manual'])
            ->assertForbidden();

        $this->assertDatabaseMissing('attendances', ['member_id' => $fixture['member']->id]);
    }

    public function test_staff_can_check_out_an_attendance(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'attendance']);
        $this->renewAsManager($fixture);
        $attendance = $this->checkInAsStaff($fixture);

        $this->from('/attendance/check-in')
            ->post("/attendance/{$attendance->id}/check-out")
            ->assertRedirect('/attendance/check-in');

        $attendance->refresh();
        $this->assertNotNull($attendance->checked_out_at);
    }

    public function test_double_check_out_is_rejected(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'attendance']);
        $this->renewAsManager($fixture);
        $attendance = $this->checkInAsStaff($fixture);

        $this->from('/attendance/check-in')
            ->post("/attendance/{$attendance->id}/check-out")
            ->assertRedirect('/attendance/check-in');

        $this->from('/attendance/check-in')
            ->post("/attendance/{$attendance->id}/check-out")
            ->assertSessionHasErrors('attendance');
    }

    public function test_attendance_page_requires_the_module(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'attendance']);
        $staff = $this->staffFor($fixture['gym']);
        $this->actAsUserInGym($staff, $fixture['gym']);

        $this->get('/attendance/check-in')->assertOk();
    }

    public function test_attendance_page_is_hidden_when_module_disabled(): void
    {
        $fixture = $this->createCommercialFixture([]);
        $staff = $this->staffFor($fixture['gym']);
        $this->actAsUserInGym($staff, $fixture['gym']);

        $this->get('/attendance/check-in')->assertNotFound();
    }

    private function renewAsManager(array $fixture): MemberMembership
    {
        $manager = User::factory()->create();
        $this->attachUser($fixture['gym'], $manager, 'manager');
        $this->actAsUserInGym($manager, $fixture['gym']);

        $this->from('/members')
            ->post("/members/{$fixture['member']->id}/renew", ['membership_plan_id' => $fixture['plan']->id])
            ->assertRedirect('/members');

        return MemberMembership::query()->where('member_id', $fixture['member']->id)->firstOrFail();
    }

    private function staffFor($gym): User
    {
        $staff = User::factory()->create();
        $this->attachUser($gym, $staff, 'staff');

        return $staff;
    }

    private function checkInAsStaff(array $fixture): Attendance
    {
        $staff = $this->staffFor($fixture['gym']);
        $this->actAsUserInGym($staff, $fixture['gym']);

        $this->from('/attendance/check-in')
            ->post("/attendance/check-in/{$fixture['member']->id}", ['method' => 'manual'])
            ->assertRedirect('/attendance/check-in');

        return Attendance::query()->where('member_id', $fixture['member']->id)->firstOrFail();
    }
}
