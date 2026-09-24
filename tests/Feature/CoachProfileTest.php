<?php

namespace Tests\Feature;

use App\Models\Coach;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class CoachProfileTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_coach_can_view_and_update_own_profile(): void
    {
        $f = $this->fixture();
        $this->actAsUserInGym($f['coachUser'], $f['gym']);

        $this->get(route('tenant.wellness.profile'))
            ->assertOk()
            ->assertSee($f['coach']->specialty);

        $this->put(route('tenant.wellness.profile.update'), [
            'mobile' => '09121112222',
            'email' => 'coach@example.test',
            'specialty' => 'بدنسازی',
            'biography' => 'مربی با تجربه',
        ])->assertRedirect();

        $this->assertDatabaseHas('coaches', [
            'id' => $f['coach']->id,
            'mobile' => '09121112222',
            'specialty' => 'بدنسازی',
        ]);
    }

    public function test_member_cannot_access_coach_profile(): void
    {
        $f = $this->fixture();
        $memberUser = User::factory()->create();
        $this->attachUser($f['gym'], $memberUser, 'member');
        $this->actAsUserInGym($memberUser, $f['gym']);

        $this->get(route('tenant.wellness.profile'))->assertForbidden();
        $this->put(route('tenant.wellness.profile.update'), ['mobile' => '09120000000'])->assertForbidden();
    }

    /**
     * @return array{gym: mixed, coach: Coach, coachUser: User}
     */
    private function fixture(): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, ['wellness']);
        $this->setGymContext($gym);

        $coachUser = User::factory()->create();
        $this->attachUser($gym, $coachUser, 'coach');
        $coach = Coach::factory()->create(['user_id' => $coachUser->id, 'coach_type' => 'training']);

        return ['gym' => $gym, 'coach' => $coach, 'coachUser' => $coachUser];
    }
}
