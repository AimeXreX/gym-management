<?php

namespace Tests\Feature;

use App\Models\BodyMeasurement;
use App\Models\Member;
use App\Models\ProgressPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_member_store_and_update_persist_height_and_weight(): void
    {
        $f = $this->fixture('manager');

        $this->post(route('tenant.members.store'), [
            'branch_id' => $f['branch']->id,
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'membership_code' => 'H-1',
            'mobile' => '09120000123',
            'joined_at' => today()->format('Y-m-d'),
            'status' => 'active',
            'height_cm' => 178,
            'weight_kg' => 82.5,
        ])->assertRedirect();

        $member = Member::query()->where('membership_code', 'H-1')->first();
        $this->assertNotNull($member);
        $this->assertEquals(178.0, (float) $member->height_cm);
        $this->assertEquals(82.5, (float) $member->weight_kg);

        $this->put(route('tenant.members.update', $member), [
            'branch_id' => $f['branch']->id,
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'membership_code' => 'H-1',
            'mobile' => '09120000123',
            'joined_at' => today()->format('Y-m-d'),
            'status' => 'active',
            'height_cm' => 180,
            'weight_kg' => 80,
        ])->assertRedirect();

        $this->assertEquals(180.0, (float) $member->refresh()->height_cm);
        $this->assertEquals(80.0, (float) $member->weight_kg);
    }

    public function test_manager_sees_body_metrics_and_measurements(): void
    {
        $f = $this->fixture('manager');
        $f['member']->update(['height_cm' => 175, 'weight_kg' => 70]);
        BodyMeasurement::query()->create([
            'member_id' => $f['member']->id,
            'recorded_by' => $f['user']->id,
            'measured_on' => '2026-08-01',
            'weight_kg' => 69.5,
            'body_fat_percent' => 15.2,
        ]);

        $this->get(route('tenant.members.show', $f['member']))
            ->assertOk()
            ->assertSee('مشخصات بدنی')
            ->assertSee('قد')
            ->assertSee('175.0')
            ->assertSee('اندازه‌گیری‌های اخیر')
            ->assertSee('69.5');
    }

    public function test_weight_chart_renders_with_two_measurements(): void
    {
        $f = $this->fixture('manager');
        foreach ([['2026-07-01', 80], ['2026-08-01', 75]] as [$date, $weight]) {
            BodyMeasurement::query()->create([
                'member_id' => $f['member']->id,
                'recorded_by' => $f['user']->id,
                'measured_on' => $date,
                'weight_kg' => $weight,
            ]);
        }

        $this->get(route('tenant.members.show', $f['member']))
            ->assertOk()
            ->assertSee('progress-chart')
            ->assertSee('polyline');
    }

    public function test_shared_photos_visible_to_manager_but_not_staff(): void
    {
        $f = $this->fixture('manager');
        ProgressPhoto::query()->create([
            'member_id' => $f['member']->id,
            'uploaded_by' => $f['user']->id,
            'captured_on' => '2026-08-01',
            'view_type' => 'front',
            'path' => 'gyms/1/members/1/progress/1.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 10,
            'visibility' => 'coaches',
            'consented_at' => now(),
        ]);

        $this->get(route('tenant.members.show', $f['member']))
            ->assertOk()
            ->assertSee('تصاویر پیشرفت');

        $staff = User::factory()->create();
        $this->attachUser($f['gym'], $staff, 'staff');
        $this->actAsUserInGym($staff, $f['gym']);

        $this->get(route('tenant.members.show', $f['member']))
            ->assertOk()
            ->assertDontSee('تصاویر پیشرفت');
    }

    public function test_manager_can_view_shared_photo_but_not_private(): void
    {
        $f = $this->fixture('manager');
        Storage::fake('local');
        $sharedPath = 'gyms/'.$f['gym']->id.'/members/'.$f['member']->id.'/progress/shared.jpg';
        $privatePath = 'gyms/'.$f['gym']->id.'/members/'.$f['member']->id.'/progress/private.jpg';
        Storage::disk('local')->put($sharedPath, 'fake-shared');
        Storage::disk('local')->put($privatePath, 'fake-private');

        $base = [
            'member_id' => $f['member']->id,
            'uploaded_by' => $f['user']->id,
            'captured_on' => '2026-08-01',
            'view_type' => 'front',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 10,
            'consented_at' => now(),
        ];
        $shared = ProgressPhoto::query()->create($base + ['path' => $sharedPath, 'visibility' => 'coaches']);
        $private = ProgressPhoto::query()->create($base + ['path' => $privatePath, 'visibility' => 'private']);

        $this->get(route('tenant.wellness.photos.show', $shared))->assertOk();
        $this->get(route('tenant.wellness.photos.show', $private))->assertForbidden();
    }

    /**
     * @return array{gym: mixed, member: Member, user: User, branch: mixed}
     */
    private function fixture(string $role): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, ['members', 'wellness']);
        $this->setGymContext($gym);
        $branch = $this->createBranch($gym);
        $member = Member::factory()->create(['branch_id' => $branch->id]);

        $user = $owner;
        if ($role !== 'manager') {
            $user = User::factory()->create();
            $this->attachUser($gym, $user, $role);
        }
        $this->actAsUserInGym($user, $gym);

        return ['gym' => $gym, 'member' => $member, 'user' => $user, 'branch' => $branch];
    }
}
