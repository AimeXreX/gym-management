<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'mobile' => '09'.fake()->numerify('#########'),
            'membership_code' => fake()->unique()->numerify('M#####'),
            'public_token' => Str::random(48),
            'status' => 'active',
            'joined_at' => now(),
        ];
    }
}
