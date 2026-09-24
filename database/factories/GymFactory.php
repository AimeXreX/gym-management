<?php

namespace Database\Factories;

use App\Models\Gym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gym>
 */
class GymFactory extends Factory
{
    protected $model = Gym::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' باشگاه',
            'slug' => fake()->unique()->slug(2),
            'status' => 'active',
            'timezone' => 'Asia/Tehran',
            'currency' => 'IRR',
            'settings_json' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inactive']);
    }
}
