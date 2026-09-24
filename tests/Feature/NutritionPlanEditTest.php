<?php

namespace Tests\Feature;

use App\Models\Coach;
use App\Models\CoachMemberAssignment;
use App\Models\Member;
use App\Models\NutritionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class NutritionPlanEditTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_nutrition_coach_can_view_and_update_plan(): void
    {
        $f = $this->fixture();
        $this->actAsUserInGym($f['nutritionUser'], $f['gym']);

        $this->get(route('tenant.wellness.plans.edit', $f['plan']))
            ->assertOk()
            ->assertSee('صبحانه');

        $this->put(route('tenant.wellness.plans.update', $f['plan']), [
            'title' => 'برنامه غذایی جدید',
            'starts_on' => today()->toDateString(),
            'items' => [
                ['meal_name' => 'ناهار', 'foods' => 'مرغ و برنج'],
            ],
        ])->assertRedirect(route('tenant.wellness.index', $f['member']));

        $this->assertDatabaseHas('nutrition_plans', ['id' => $f['plan']->id, 'title' => 'برنامه غذایی جدید']);
        $this->assertDatabaseHas('nutrition_plan_items', ['meal_name' => 'ناهار']);
        $this->assertDatabaseMissing('nutrition_plan_items', ['meal_name' => 'صبحانه']);
    }

    public function test_training_coach_cannot_edit_nutrition_plan(): void
    {
        $f = $this->fixture();
        $this->actAsUserInGym($f['trainingUser'], $f['gym']);

        $this->get(route('tenant.wellness.plans.edit', $f['plan']))->assertForbidden();
        $this->put(route('tenant.wellness.plans.update', $f['plan']), [
            'title' => 'ویرایش غیرمجاز',
            'starts_on' => today()->toDateString(),
            'items' => [['meal_name' => 'شام', 'foods' => 'سوپ']],
        ])->assertForbidden();
    }

    /**
     * @return array{gym: mixed, member: Member, nutrition: Coach, nutritionUser: User, training: Coach, trainingUser: User, plan: NutritionPlan}
     */
    private function fixture(): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, ['wellness']);
        $this->setGymContext($gym);
        $branch = $this->createBranch($gym);
        $member = Member::factory()->create(['branch_id' => $branch->id]);

        $nutritionUser = User::factory()->create();
        $this->attachUser($gym, $nutritionUser, 'nutrition_coach');
        $nutrition = Coach::factory()->create(['user_id' => $nutritionUser->id, 'coach_type' => 'nutrition']);
        CoachMemberAssignment::query()->create(['coach_id' => $nutrition->id, 'member_id' => $member->id, 'domain' => 'nutrition', 'is_active' => true]);

        $trainingUser = User::factory()->create();
        $this->attachUser($gym, $trainingUser, 'coach');
        $training = Coach::factory()->create(['user_id' => $trainingUser->id, 'coach_type' => 'training']);
        CoachMemberAssignment::query()->create(['coach_id' => $training->id, 'member_id' => $member->id, 'domain' => 'training', 'is_active' => true]);

        $plan = NutritionPlan::query()->create(['member_id' => $member->id, 'created_by' => $nutritionUser->id, 'title' => 'برنامه غذایی', 'starts_on' => today()->toDateString(), 'status' => 'active']);
        $plan->items()->create(['meal_name' => 'صبحانه', 'foods' => 'تخم‌مرغ', 'sort_order' => 0]);

        return ['gym' => $gym, 'member' => $member, 'nutrition' => $nutrition, 'nutritionUser' => $nutritionUser, 'training' => $training, 'trainingUser' => $trainingUser, 'plan' => $plan];
    }
}
