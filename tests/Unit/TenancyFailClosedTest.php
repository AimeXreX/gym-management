<?php

namespace Tests\Unit;

use App\Core\Tenancy\Exceptions\MissingGymContext;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class TenancyFailClosedTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_query_without_context_throws(): void
    {
        $this->expectException(MissingGymContext::class);

        Member::query()->count();
    }

    public function test_create_auto_fills_gym_id_from_context(): void
    {
        [$gym] = $this->createGymWithOwner();
        $this->setGymContext($gym);

        $member = Member::factory()->create();

        $this->assertSame($gym->getKey(), $member->gym_id);
    }

    public function test_members_are_isolated_between_gyms(): void
    {
        [$gymA] = $this->createGymWithOwner();
        [$gymB] = $this->createGymWithOwner();

        $this->setGymContext($gymA);
        $memberA = Member::factory()->create(['membership_code' => 'AAA-1']);

        $this->setGymContext($gymB);
        $memberB = Member::factory()->create(['membership_code' => 'BBB-1']);

        $this->setGymContext($gymA);
        $this->assertSame(1, Member::query()->count());
        $this->assertTrue(Member::query()->whereKey($memberA->id)->exists());
        $this->assertFalse(Member::query()->whereKey($memberB->id)->exists());

        $this->setGymContext($gymB);
        $this->assertSame(1, Member::query()->count());
        $this->assertTrue(Member::query()->whereKey($memberB->id)->exists());
    }

    public function test_changing_gym_id_on_update_is_forbidden(): void
    {
        [$gymA] = $this->createGymWithOwner();
        [$gymB] = $this->createGymWithOwner();
        $this->setGymContext($gymA);
        $member = Member::factory()->create();

        $this->expectException(LogicException::class);

        // gym_id is intentionally not fillable; set it directly to exercise the guard.
        $member->gym_id = $gymB->id;
        $member->save();
    }
}
