<?php

namespace Tests\Unit;

use App\Models\Coach;
use App\Models\Member;
use App\Models\Notification;
use App\Models\User;
use App\Modules\Commercial\Application\CommercialWorkflows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class NotificationWorkflowTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    private CommercialWorkflows $workflows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflows = app(CommercialWorkflows::class);
    }

    public function test_request_notifies_reviewer_and_target_coach(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'training', 'user_id' => $f['coachUser']->id]);

        $this->workflows->requestCoach($f['member'], $coach, 'training', 'assign', null, $f['request']);

        $this->assertDatabaseHas('notifications', ['user_id' => $f['owner']->id, 'type' => 'coach_request.created']);
        $this->assertDatabaseHas('notifications', ['user_id' => $f['coachUser']->id, 'type' => 'coach_request.created']);
    }

    public function test_review_notifies_member(): void
    {
        $f = $this->fixture();
        $f['member']->update(['user_id' => $f['memberUser']->id]);
        $req = $this->workflows->requestCoach($f['member'], null, 'training', 'program', null, $f['request']);

        $this->workflows->reviewCoachRequest($req, true, null, $f['request']);

        $this->assertDatabaseHas('notifications', ['user_id' => $f['memberUser']->id, 'type' => 'coach_request.reviewed']);
    }

    public function test_coach_program_creation_notifies_member(): void
    {
        $f = $this->fixture();
        $f['member']->update(['user_id' => $f['memberUser']->id]);
        $coach = Coach::factory()->create(['coach_type' => 'training']);

        $this->workflows->createWorkoutProgram($f['member'], $coach, $this->programData(), $f['request']);

        $this->assertDatabaseHas('notifications', ['user_id' => $f['memberUser']->id, 'type' => 'workout_program.created']);
    }

    public function test_publishing_program_notifies_member(): void
    {
        $f = $this->fixture();
        $f['member']->update(['user_id' => $f['memberUser']->id]);
        $coach = Coach::factory()->create(['coach_type' => 'training']);
        $program = $this->workflows->createWorkoutProgram($f['member'], $coach, $this->programData(), $f['request']);

        Notification::query()->delete();

        $this->workflows->updateWorkoutProgramStatus($program, 'active', $f['request']);

        $this->assertDatabaseHas('notifications', ['user_id' => $f['memberUser']->id, 'type' => 'workout_program.published']);
    }

    public function test_member_self_program_does_not_notify(): void
    {
        $f = $this->fixture();
        $f['member']->update(['user_id' => $f['memberUser']->id]);

        $this->workflows->createWorkoutProgram($f['member'], null, $this->programData(), $f['request']);

        $this->assertSame(0, Notification::query()->count());
    }

    private function programData(): array
    {
        return [
            'title' => 'برنامه تست',
            'starts_on' => today()->toDateString(),
            'ends_on' => null,
            'goal' => null,
            'notes' => null,
            'days' => [
                [
                    'day_number' => 1,
                    'title' => 'روز ۱',
                    'notes' => null,
                    'sets' => [
                        ['exercise_name' => 'اسکات', 'set_number' => 1, 'weight_kg' => 50, 'repetitions' => 10],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{gym: mixed, owner: User, member: Member, coachUser: User, memberUser: User, request: Request}
     */
    private function fixture(): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->setGymContext($gym);
        $branch = $this->createBranch($gym);
        $member = Member::factory()->create(['branch_id' => $branch->id]);
        $coachUser = User::factory()->create();
        $memberUser = User::factory()->create();
        $request = Request::create('/test', 'POST');
        $request->setUserResolver(fn () => $owner);

        return ['gym' => $gym, 'owner' => $owner, 'member' => $member, 'coachUser' => $coachUser, 'memberUser' => $memberUser, 'request' => $request];
    }
}
