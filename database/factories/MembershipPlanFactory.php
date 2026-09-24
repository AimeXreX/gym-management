<?php

namespace Database\Factories;

use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    protected $model = MembershipPlan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' طرح',
            'duration_days' => 30,
            'session_limit' => null,
            'price' => 100,
            'currency' => 'IRR',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
