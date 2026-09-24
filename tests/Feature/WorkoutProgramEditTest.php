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

class WorkoutProgramEditTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_training_coach_can_view_and_update_program(): void
    {
        $f = $this->fixture();
        $this->actAsUserInGym($f['coachUser'], $f['gym']);

        $this->get(route('tenant.wellness.programs.edit', $f['program']))
            ->assertOk()
            ->assertSee('روز پا');

        $this->put(route('tenant.wellness.programs.update', $f['program']), [
            'title' => 'برنامه جدید',
            'starts_on' => today()->toDateString(),
            'days' => [
                ['day_number' => 1, 'title' => 'روز سینه', 'sets' => [['exercise_name' => 'پرس سینه', 'set_number' => 1, 'weight_kg' => 60, 'repetitions' => 12]]],
            ],
        ])->assertRedirect(route('tenant.wellness.index', $f['member']));

        $this->assertDatabaseHas('workout_programs', ['id' => $f['program']->id, 'title' => 'برنامه جدید']);
        $this->assertDatabaseHas('workout_program_sets', ['exercise_name' => 'پرس سینه']);
        $this->assertDatabaseMissing('workout_program_sets', ['exercise_name' => 'اسکوات']);
    }

    public function test_member_cannot_edit_coach_program(): void
    {
        $f = $this->fixture();
        $this->actAsUserInGym($f['memberUser'], $f['gym']);

        $this->get(route('tenant.wellness.programs.edit', $f['program']))->assertForbidden();
        $this->put(route('tenant.wellness.programs.update', $f['program']), [
            'title' => 'ویرایش غیرمجاز',
            'starts_on' => today()->toDateString(),
            'days' => [['day_number' => 1, 'sets' => [['exercise_name' => 'شنا', 'set_number' => 1]]]],
        ])->assertForbidden();
    }

    public function test_member_can_edit_own_program(): void
    {
        $f = $this->fixture(true);
        $this->actAsUserInGym($f['memberUser'], $f['gym']);

        $this->get(route('tenant.wellness.programs.edit', $f['program']))->assertOk();

        $this->put(route('tenant.wellness.programs.update', $f['program']), [
            'title' => 'برنامه شخصی جدید',
            'starts_on' => today()->toDateString(),
            'days' => [['day_number' => 2, 'sets' => [['exercise_name' => 'بارفیکس', 'set_number' => 1]]]],
        ])->assertRedirect();

        $this->assertDatabaseHas('workout_programs', ['id' => $f['program']->id, 'title' => 'برنامه شخصی جدید']);
    }

    public function test_coach_and_member_can_add_feedback(): void
    {
        $f = $this->fixture();

        $this->actAsUserInGym($f['coachUser'], $f['gym']);
        $this->post(route('tenant.wellness.programs.feedback', $f['program']), ['body' => 'روز اول را سبک‌تر انجام بده.'])->assertRedirect();
        $this->assertDatabaseHas('workout_program_feedback', ['user_id' => $f['coachUser']->id, 'body' => 'روز اول را سبک‌تر انجام بده.']);

        $this->actAsUserInGym($f['memberUser'], $f['gym']);
        $this->post(route('tenant.wellness.programs.feedback', $f['program']), ['body' => 'باشه ممنون.'])->assertRedirect();
        $this->assertDatabaseHas('workout_program_feedback', ['user_id' => $f['memberUser']->id]);
    }

    public function test_non_owner_member_cannot_add_feedback(): void
    {
        $f = $this->fixture();
        $other = Member::factory()->create(['branch_id' => $this->createBranch($f['gym'])->id]);
        $otherUser = User::factory()->create();
        $this->attachUser($f['gym'], $otherUser, 'member');
        $other->update(['user_id' => $otherUser->id]);
        $this->actAsUserInGym($otherUser, $f['gym']);

        $this->post(route('tenant.wellness.programs.feedback', $f['program']), ['body' => 'نفوذ'])->assertForbidden();
    }

    public function test_workout_session_links_to_program_day(): void
    {
        $f = $this->fixture();
        $this->actAsUserInGym($f['memberUser'], $f['gym']);

        $this->post(route('tenant.wellness.workouts', $f['member']), [
            'performed_on' => today()->toDateString(),
            'program_day_id' => $f['day']->id,
            'sets' => [['exercise_name' => 'اسکوات', 'set_number' => 1]],
        ])->assertRedirect();

        $this->assertDatabaseHas('workout_sessions', [
            'member_id' => $f['member']->id,
            'program_id' => $f['program']->id,
            'program_day_id' => $f['day']->id,
        ]);
    }

    public function test_session_rejects_program_day_from_other_member(): void
    {
        $f = $this->fixture();
        $otherMember = Member::factory()->create(['branch_id' => $this->createBranch($f['gym'])->id]);
        $otherProgram = WorkoutProgram::query()->create([
            'member_id' => $otherMember->id,
            'created_by' => $f['owner']->id,
            'title' => 'برنامه دیگری',
            'starts_on' => today()->toDateString(),
            'status' => 'draft',
        ]);
        $otherDay = $otherProgram->days()->create(['day_number' => 1]);

        $this->actAsUserInGym($f['memberUser'], $f['gym']);
        $this->post(route('tenant.wellness.workouts', $f['member']), [
            'performed_on' => today()->toDateString(),
            'program_day_id' => $otherDay->id,
            'sets' => [['exercise_name' => 'اسکوات', 'set_number' => 1]],
        ])->assertSessionHasErrors('program_day_id');
    }

    public function test_wellness_index_renders_execution_and_feedback(): void
    {
        $f = $this->fixture();
        $f['day']->sessions()->create([
            'member_id' => $f['member']->id,
            'recorded_by' => $f['memberUser']->id,
            'performed_on' => today()->toDateString(),
            'program_id' => $f['program']->id,
            'program_day_id' => $f['day']->id,
        ]);
        $f['program']->feedback()->create(['user_id' => $f['coachUser']->id, 'body' => 'روز اول را سبک کن']);

        $this->actAsUserInGym($f['coachUser'], $f['gym']);
        $this->get(route('tenant.wellness.index'))
            ->assertOk()
            ->assertSee('اجرا شده')
            ->assertSee('روز اول را سبک کن')
            ->assertSee('ویرایش')
            ->assertSee('اجرای روز برنامه');
    }

    /**
     * @return array{gym: mixed, owner: User, member: Member, memberUser: User, coach: Coach, coachUser: User, program: WorkoutProgram, day: mixed}
     */
    private function fixture(bool $memberOwned = false): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, ['wellness']);
        $this->setGymContext($gym);
        $branch = $this->createBranch($gym);

        $memberUser = User::factory()->create();
        $this->attachUser($gym, $memberUser, 'member');
        $member = Member::factory()->create(['branch_id' => $branch->id, 'user_id' => $memberUser->id]);

        $coachUser = User::factory()->create();
        $this->attachUser($gym, $coachUser, 'coach');
        $coach = Coach::factory()->create(['user_id' => $coachUser->id, 'coach_type' => 'training']);
        CoachMemberAssignment::query()->create(['coach_id' => $coach->id, 'member_id' => $member->id, 'domain' => 'training', 'is_active' => true]);

        $program = WorkoutProgram::query()->create([
            'member_id' => $member->id,
            'coach_id' => $memberOwned ? null : $coach->id,
            'created_by' => $memberOwned ? $memberUser->id : $coachUser->id,
            'title' => 'برنامه تست',
            'starts_on' => today()->toDateString(),
            'status' => 'draft',
        ]);
        $day = $program->days()->create(['day_number' => 1, 'title' => 'روز پا']);
        $day->sets()->create(['exercise_name' => 'اسکوات', 'set_number' => 1, 'weight_kg' => 100, 'repetitions' => 10]);

        return ['gym' => $gym, 'owner' => $owner, 'member' => $member, 'memberUser' => $memberUser, 'coach' => $coach, 'coachUser' => $coachUser, 'program' => $program, 'day' => $day];
    }
}
