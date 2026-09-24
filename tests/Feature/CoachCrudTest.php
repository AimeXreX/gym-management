<?php

namespace Tests\Feature;

use App\Models\Coach;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class CoachCrudTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_owner_can_create_coach_with_full_profile_and_account(): void
    {
        $f = $this->fixture();
        $coachUser = User::factory()->create();
        $this->attachUser($f['gym'], $coachUser, 'coach');

        $this->post(route('tenant.coaches.store'), [
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'mobile' => '09120000099',
            'email' => 'ali@gym.test',
            'specialty' => 'قدرتی',
            'coach_type' => 'training',
            'biography' => 'مربی بدنسازی',
            'hire_date' => '2026-01-01',
            'compensation_notes' => 'حقوق ماهانه',
            'user_id' => $coachUser->id,
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('coaches', [
            'first_name' => 'علی',
            'biography' => 'مربی بدنسازی',
            'compensation_notes' => 'حقوق ماهانه',
            'user_id' => $coachUser->id,
        ]);
        $coach = Coach::query()->where('mobile', '09120000099')->first();
        $this->assertNotNull($coach);
        $this->assertSame('2026-01-01', $coach->hire_date->format('Y-m-d'));
    }

    public function test_cannot_link_coach_to_user_already_linked_to_another_coach(): void
    {
        $f = $this->fixture();
        $coachUser = User::factory()->create();
        $this->attachUser($f['gym'], $coachUser, 'coach');
        Coach::factory()->create(['user_id' => $coachUser->id]);

        $this->post(route('tenant.coaches.store'), [
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'mobile' => '09120000099',
            'coach_type' => 'training',
            'status' => 'active',
            'user_id' => $coachUser->id,
        ])->assertSessionHasErrors('user_id');
    }

    public function test_cannot_link_coach_to_user_outside_gym(): void
    {
        $f = $this->fixture();
        $outsider = User::factory()->create();

        $this->post(route('tenant.coaches.store'), [
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'mobile' => '09120000099',
            'coach_type' => 'training',
            'status' => 'active',
            'user_id' => $outsider->id,
        ])->assertSessionHasErrors('user_id');
    }

    public function test_owner_can_update_coach_profile_fields(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create();
        $coachUser = User::factory()->create();
        $this->attachUser($f['gym'], $coachUser, 'coach');

        $this->put(route('tenant.coaches.update', $coach), [
            'first_name' => $coach->first_name,
            'last_name' => $coach->last_name,
            'mobile' => $coach->mobile,
            'coach_type' => 'both',
            'biography' => 'بیوگرافی جدید',
            'hire_date' => '2026-02-01',
            'compensation_notes' => 'توافق جدید',
            'user_id' => $coachUser->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('coaches', [
            'id' => $coach->id,
            'coach_type' => 'both',
            'biography' => 'بیوگرافی جدید',
            'compensation_notes' => 'توافق جدید',
            'user_id' => $coachUser->id,
        ]);
        $this->assertSame('2026-02-01', $coach->refresh()->hire_date->format('Y-m-d'));
    }

    public function test_compensation_notes_visible_only_to_managers(): void
    {
        $f = $this->fixture();
        $coach = Coach::factory()->create(['compensation_notes' => 'اطلاعات محرمانه دستمزد']);

        $this->get(route('tenant.coaches.show', $coach))
            ->assertOk()
            ->assertSee('اطلاعات محرمانه دستمزد');

        $staff = User::factory()->create();
        $this->attachUser($f['gym'], $staff, 'staff');
        $this->actAsUserInGym($staff, $f['gym']);

        $this->get(route('tenant.coaches.show', $coach))
            ->assertOk()
            ->assertDontSee('اطلاعات محرمانه دستمزد');
    }

    /**
     * @return array{gym: mixed, owner: User}
     */
    private function fixture(): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, ['coaches']);
        $this->setGymContext($gym);
        $this->createBranch($gym);
        $this->actAsUserInGym($owner, $gym);

        return ['gym' => $gym, 'owner' => $owner];
    }
}
