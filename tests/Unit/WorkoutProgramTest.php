<?php

namespace Tests\Unit;

use App\Models\Coach;
use App\Models\Member;
use App\Modules\Commercial\Application\CommercialWorkflows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class WorkoutProgramTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    private CommercialWorkflows $workflows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflows = app(CommercialWorkflows::class);
    }

    public function test_create_program_persists_days_and_sets(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['coach_type' => 'training']);

        $program = $this->workflows->createWorkoutProgram($f['member'], $coach, [
            'title' => 'برنامه حجم',
            'starts_on' => today()->toDateString(),
            'goal' => 'افزایش حجم',
            'days' => [
                ['day_number' => 1, 'title' => 'سینه', 'sets' => [
                    ['exercise_name' => 'پرس سینه', 'set_number' => 1, 'weight_kg' => 60, 'repetitions' => 10],
                ]],
                ['day_number' => 2, 'title' => 'پا', 'sets' => [
                    ['exercise_name' => 'اسکات', 'set_number' => 1, 'weight_kg' => 80, 'repetitions' => 8],
                    ['exercise_name' => 'اسکات', 'set_number' => 2, 'weight_kg' => 80, 'repetitions' => 8],
                ]],
            ],
        ], $f['request']);

        $this->assertSame('draft', $program->status);
        $this->assertSame($coach->id, $program->coach_id);
        $this->assertSame($f['member']->id, $program->member_id);
        $program->load('days.sets');
        $this->assertCount(2, $program->days);
        $this->assertCount(1, $program->days->first()->sets);
        $this->assertCount(2, $program->days->last()->sets);
    }

    public function test_create_program_without_coach_is_personal(): void
    {
        $f = $this->fixture();

        $program = $this->workflows->createWorkoutProgram($f['member'], null, [
            'title' => 'برنامه شخصی',
            'starts_on' => today()->toDateString(),
            'days' => [['day_number' => 1, 'sets' => [['exercise_name' => 'شنا', 'set_number' => 1]]]],
        ], $f['request']);

        $this->assertNull($program->coach_id);
        $this->assertSame($f['member']->id, $program->member_id);
    }

    public function test_update_status_accepts_valid_transitions(): void
    {
        $f = $this->fixture();
        $program = $this->workflows->createWorkoutProgram($f['member'], null, [
            'title' => 'برنامه',
            'starts_on' => today()->toDateString(),
            'days' => [['day_number' => 1, 'sets' => [['exercise_name' => 'شنا', 'set_number' => 1]]]],
        ], $f['request']);

        $this->workflows->updateWorkoutProgramStatus($program, 'active', $f['request']);

        $this->assertSame('active', $program->refresh()->status);
    }

    public function test_update_status_rejects_invalid_status(): void
    {
        $f = $this->fixture();
        $program = $this->workflows->createWorkoutProgram($f['member'], null, [
            'title' => 'برنامه',
            'starts_on' => today()->toDateString(),
            'days' => [['day_number' => 1, 'sets' => [['exercise_name' => 'شنا', 'set_number' => 1]]]],
        ], $f['request']);

        $this->expectException(ValidationException::class);
        $this->workflows->updateWorkoutProgramStatus($program, 'weird', $f['request']);
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
