<?php

namespace Tests\Unit;

use App\Models\ClassSession;
use App\Models\Coach;
use App\Models\GymClass;
use App\Models\User;
use App\Modules\Commercial\Application\CommercialWorkflows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class ClassNotificationTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    private CommercialWorkflows $workflows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflows = app(CommercialWorkflows::class);
    }

    public function test_scheduled_class_notifies_coach(): void
    {
        $f = $this->fixture();

        $this->workflows->notifyClassSession($f['session'], 'scheduled');

        $this->assertDatabaseHas('notifications', ['user_id' => $f['coachUser']->id, 'type' => 'class_session.scheduled']);
    }

    public function test_cancelled_class_notifies_coach(): void
    {
        $f = $this->fixture();

        $this->workflows->notifyClassSession($f['session'], 'cancelled');

        $this->assertDatabaseHas('notifications', ['user_id' => $f['coachUser']->id, 'type' => 'class_session.cancelled']);
    }

    public function test_no_notification_when_coach_has_no_user(): void
    {
        $f = $this->fixture();
        $f['coach']->update(['user_id' => null]);

        $this->workflows->notifyClassSession($f['session'], 'scheduled');

        $this->assertDatabaseMissing('notifications', ['type' => 'class_session.scheduled']);
    }

    /**
     * @return array{gym: mixed, coach: Coach, coachUser: User, session: ClassSession}
     */
    private function fixture(): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->setGymContext($gym);
        $branch = $this->createBranch($gym);
        $coachUser = User::factory()->create();
        $coach = Coach::factory()->create(['user_id' => $coachUser->id, 'coach_type' => 'training']);
        $gymClass = GymClass::query()->create(['branch_id' => $branch->id, 'coach_id' => $coach->id, 'name' => 'کلاس تست', 'capacity' => 10, 'duration_minutes' => 60, 'status' => 'active']);
        $session = ClassSession::query()->create(['gym_class_id' => $gymClass->id, 'class_schedule_id' => null, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'status' => 'scheduled']);

        return ['gym' => $gym, 'coach' => $coach, 'coachUser' => $coachUser, 'session' => $session];
    }
}
