<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Coach;
use App\Models\CoachMemberAssignment;
use App\Models\CoachRequest;
use App\Models\GymClass;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class CoachDashboardTest extends TestCase
{
    use InteractsWithGym, RefreshDatabase;

    public function test_coach_sees_roster_pending_requests_and_today_classes(): void
    {
        $f = $this->fixture();

        CoachRequest::query()->create([
            'member_id' => $f['member']->id,
            'coach_id' => $f['coach']->id,
            'domain' => 'training',
            'type' => 'assign',
            'status' => 'pending',
            'message' => 'درخواست تست',
        ]);
        $gymClass = GymClass::query()->create([
            'branch_id' => $f['branch']->id,
            'coach_id' => $f['coach']->id,
            'name' => 'کلاس تست',
            'capacity' => 10,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
        ClassSession::query()->create([
            'gym_class_id' => $gymClass->id,
            'class_schedule_id' => null,
            'starts_at' => today()->setTime(10, 0),
            'ends_at' => today()->setTime(11, 0),
            'status' => 'scheduled',
        ]);

        $this->get(route('tenant.wellness.dashboard'))
            ->assertOk()
            ->assertSee($f['member']->full_name)
            ->assertSee('کلاس تست')
            ->assertSee('درخواست تست');
    }

    public function test_member_cannot_access_coach_dashboard(): void
    {
        $f = $this->fixture();

        $memberUser = User::factory()->create();
        $this->attachUser($f['gym'], $memberUser, 'member');
        $this->actAsUserInGym($memberUser, $f['gym']);

        $this->get(route('tenant.wellness.dashboard'))->assertForbidden();
    }

    public function test_dual_domain_coach_sees_training_and_nutrition_tools(): void
    {
        $f = $this->fixture();
        $f['coach']->update(['coach_type' => 'both']);
        CoachMemberAssignment::query()->create([
            'coach_id' => $f['coach']->id,
            'member_id' => $f['member']->id,
            'domain' => 'nutrition',
            'is_active' => true,
        ]);

        $this->get(route('tenant.wellness.dashboard'))
            ->assertOk()
            ->assertSee('مربی تمرین')
            ->assertSee('مربی تغذیه')
            ->assertSee('قالب‌های تمرینی')
            ->assertSee('کتابخانه حرکات');
    }

    public function test_inactive_coach_cannot_access_coach_dashboard(): void
    {
        $f = $this->fixture();
        $f['coach']->update(['status' => 'inactive']);

        $this->get(route('tenant.wellness.dashboard'))->assertForbidden();
    }

    /**
     * @return array{gym: mixed, coach: Coach, member: Member, branch: mixed}
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
        CoachMemberAssignment::query()->create([
            'coach_id' => $coach->id,
            'member_id' => $member->id,
            'domain' => 'training',
            'is_active' => true,
        ]);

        $this->actAsUserInGym($coachUser, $gym);

        return ['gym' => $gym, 'coach' => $coach, 'member' => $member, 'branch' => $branch];
    }
}
