<?php

namespace Tests\Feature;

use App\Models\Coach;
use App\Models\CoachMemberAssignment;
use App\Models\Member;
use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class WorkoutProgramTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_training_coach_can_create_program(): void
    {
        $f = $this->fixture('coach');
        $coach = Coach::factory()->create(['coach_type' => 'training', 'user_id' => $f['user']->id]);
        CoachMemberAssignment::query()->create(['coach_id' => $coach->id, 'member_id' => $f['member']->id, 'domain' => 'training']);

        $this->post(route('tenant.wellness.programs', $f['member']), [
            'title' => 'برنامه حجم',
            'starts_on' => today()->toDateString(),
            'days' => [['day_number' => 1, 'sets' => [['exercise_name' => 'پرس سینه', 'set_number' => 1]]]],
        ])->assertRedirect();

        $this->assertDatabaseHas('workout_programs', [
            'member_id' => $f['member']->id,
            'coach_id' => $coach->id,
            'title' => 'برنامه حجم',
        ]);
    }

    public function test_nutrition_coach_cannot_create_training_program(): void
    {
        $f = $this->fixture('nutrition_coach');
        $coach = Coach::factory()->create(['coach_type' => 'nutrition', 'user_id' => $f['user']->id]);
        CoachMemberAssignment::query()->create(['coach_id' => $coach->id, 'member_id' => $f['member']->id, 'domain' => 'nutrition']);

        $this->post(route('tenant.wellness.programs', $f['member']), [
            'title' => 'برنامه',
            'starts_on' => today()->toDateString(),
            'days' => [['day_number' => 1, 'sets' => [['exercise_name' => 'شنا', 'set_number' => 1]]]],
        ])->assertForbidden();

        $this->assertDatabaseMissing('workout_programs', ['title' => 'برنامه']);
    }

    public function test_member_can_create_own_program(): void
    {
        $f = $this->fixture('member');
        $f['member']->update(['user_id' => $f['user']->id]);

        $this->post(route('tenant.wellness.programs', $f['member']), [
            'title' => 'برنامه شخصی',
            'starts_on' => today()->toDateString(),
            'days' => [['day_number' => 1, 'sets' => [['exercise_name' => 'شنا', 'set_number' => 1]]]],
        ])->assertRedirect();

        $this->assertDatabaseHas('workout_programs', ['member_id' => $f['member']->id, 'title' => 'برنامه شخصی']);
    }

    public function test_member_cannot_change_coach_program_status(): void
    {
        $f = $this->fixture('member');
        $f['member']->update(['user_id' => $f['user']->id]);
        $coach = Coach::factory()->create(['coach_type' => 'training']);
        $program = WorkoutProgram::query()->create([
            'member_id' => $f['member']->id,
            'coach_id' => $coach->id,
            'created_by' => $f['user']->id,
            'title' => 'برنامه مربی',
            'starts_on' => today()->toDateString(),
            'status' => 'draft',
        ]);

        $this->patch(route('tenant.wellness.programs.status', $program), ['status' => 'active'])->assertForbidden();
    }

    /**
     * @return array{member: Member, user: User}
     */
    private function fixture(string $role): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, ['wellness']);
        $this->setGymContext($gym);
        $branch = $this->createBranch($gym);
        $member = Member::factory()->create(['branch_id' => $branch->id]);

        $user = User::factory()->create();
        $this->attachUser($gym, $user, $role);
        $this->actAsUserInGym($user, $gym);

        return ['member' => $member, 'user' => $user];
    }
}
