<?php

namespace Tests\Feature;

use App\Models\Coach;
use App\Models\CoachMemberAssignment;
use App\Models\Member;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class WorkoutTemplateTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_training_coach_can_create_exercise(): void
    {
        $f = $this->fixture();
        $this->actAsUserInGym($f['coachUser'], $f['gym']);

        $this->post(route('tenant.wellness.exercises.store'), ['name' => 'اسکوات', 'category' => 'strength'])->assertRedirect();
        $this->assertDatabaseHas('exercises', ['name' => 'اسکوات', 'category' => 'strength']);
    }

    public function test_training_coach_can_create_template_with_phases(): void
    {
        $f = $this->fixture();
        $this->actAsUserInGym($f['coachUser'], $f['gym']);

        $this->post(route('tenant.wellness.templates.store'), [
            'name' => 'هوازی ماه اول',
            'days' => [
                ['phase' => 'ماه اول', 'day_number' => 1, 'sets' => [['exercise_name' => 'دوچرخه', 'set_number' => 1, 'duration_seconds' => 1200]]],
                ['phase' => 'ماه دوم', 'day_number' => 1, 'sets' => [['exercise_name' => 'تردمیل', 'set_number' => 1, 'duration_seconds' => 1800]]],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('workout_templates', ['name' => 'هوازی ماه اول', 'coach_id' => $f['coach']->id]);
        $this->assertDatabaseHas('workout_template_days', ['phase' => 'ماه اول']);
        $this->assertDatabaseHas('workout_template_sets', ['exercise_name' => 'تردمیل', 'duration_seconds' => 1800]);
    }

    public function test_coach_can_assign_template_to_assigned_member(): void
    {
        $f = $this->fixture();
        $this->actAsUserInGym($f['coachUser'], $f['gym']);
        $template = $this->makeTemplate($f['coach']->id, $f['owner']->id);

        $this->post(route('tenant.wellness.templates.assign', $template), [
            'member_ids' => [$f['member']->id],
            'starts_on' => today()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('workout_programs', ['member_id' => $f['member']->id, 'title' => 'قالب تست']);
        $this->assertDatabaseHas('notifications', ['user_id' => $f['memberUser']->id, 'type' => 'workout_program.created']);
    }

    public function test_coach_cannot_assign_to_unassigned_member(): void
    {
        $f = $this->fixture();
        $this->actAsUserInGym($f['coachUser'], $f['gym']);
        $template = $this->makeTemplate($f['coach']->id, $f['owner']->id);
        $other = Member::factory()->create(['branch_id' => $this->createBranch($f['gym'])->id]);

        $this->post(route('tenant.wellness.templates.assign', $template), [
            'member_ids' => [$other->id],
            'starts_on' => today()->toDateString(),
        ])->assertStatus(422);

        $this->assertDatabaseMissing('workout_programs', ['member_id' => $other->id]);
    }

    public function test_nutrition_coach_cannot_access_templates(): void
    {
        $f = $this->fixture('nutrition');
        $this->actAsUserInGym($f['coachUser'], $f['gym']);

        $this->get(route('tenant.wellness.templates.index'))->assertForbidden();
        $this->post(route('tenant.wellness.templates.store'), [
            'name' => 'غیرمجاز',
            'days' => [['day_number' => 1, 'sets' => [['exercise_name' => 'شنا', 'set_number' => 1]]]],
        ])->assertForbidden();
    }

    public function test_manager_can_create_club_template_and_assign_to_any_member(): void
    {
        $f = $this->fixture();
        $this->actAsUserInGym($f['owner'], $f['gym']);

        $this->post(route('tenant.wellness.templates.store'), [
            'name' => 'قالب باشگاه',
            'days' => [['day_number' => 1, 'sets' => [['exercise_name' => 'شنا', 'set_number' => 1]]]],
        ])->assertRedirect();
        $this->assertDatabaseHas('workout_templates', ['name' => 'قالب باشگاه', 'coach_id' => null]);

        $template = WorkoutTemplate::query()->where('name', 'قالب باشگاه')->firstOrFail();
        $other = Member::factory()->create(['branch_id' => $this->createBranch($f['gym'])->id]);
        $this->post(route('tenant.wellness.templates.assign', $template), [
            'member_ids' => [$other->id],
            'starts_on' => today()->toDateString(),
        ])->assertRedirect();
        $this->assertDatabaseHas('workout_programs', ['member_id' => $other->id, 'title' => 'قالب باشگاه']);
    }

    public function test_coach_cannot_assign_other_coach_private_template(): void
    {
        $f = $this->fixture();
        $otherCoach = Coach::factory()->create(['coach_type' => 'training']);
        $template = $this->makeTemplate($otherCoach->id, $f['owner']->id);
        $this->actAsUserInGym($f['coachUser'], $f['gym']);

        $this->post(route('tenant.wellness.templates.assign', $template), [
            'member_ids' => [$f['member']->id],
            'starts_on' => today()->toDateString(),
        ])->assertForbidden();
    }

    public function test_member_cannot_access_templates(): void
    {
        $f = $this->fixture();
        $this->actAsUserInGym($f['memberUser'], $f['gym']);

        $this->get(route('tenant.wellness.templates.index'))->assertForbidden();
    }

    private function makeTemplate(int $coachId, int $createdBy): WorkoutTemplate
    {
        $template = WorkoutTemplate::query()->create(['coach_id' => $coachId, 'name' => 'قالب تست', 'created_by' => $createdBy]);
        $day = $template->days()->create(['day_number' => 1, 'phase' => 'ماه اول', 'title' => 'روز پا']);
        $day->sets()->create(['exercise_name' => 'اسکوات', 'set_number' => 1, 'weight_kg' => 100, 'repetitions' => 10]);

        return $template;
    }

    /**
     * @return array{gym: mixed, owner: User, member: Member, memberUser: User, coach: Coach, coachUser: User}
     */
    private function fixture(string $coachType = 'training'): array
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
        $coach = Coach::factory()->create(['user_id' => $coachUser->id, 'coach_type' => $coachType]);
        if ($coachType !== 'nutrition') {
            CoachMemberAssignment::query()->create(['coach_id' => $coach->id, 'member_id' => $member->id, 'domain' => 'training', 'is_active' => true]);
        }

        return ['gym' => $gym, 'owner' => $owner, 'member' => $member, 'memberUser' => $memberUser, 'coach' => $coach, 'coachUser' => $coachUser];
    }
}
