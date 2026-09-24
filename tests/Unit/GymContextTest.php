<?php

namespace Tests\Unit;

use App\Core\Tenancy\Exceptions\MissingGymContext;
use App\Core\Tenancy\GymContext;
use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GymContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_is_empty_by_default(): void
    {
        $this->assertFalse(app(GymContext::class)->has());
    }

    public function test_set_and_retrieve_gym(): void
    {
        $gym = Gym::factory()->create();
        $context = app(GymContext::class);

        $context->set($gym);

        $this->assertTrue($context->has());
        $this->assertSame($gym->getKey(), $context->id());
        $this->assertTrue($context->gym()->is($gym));
    }

    public function test_clear_removes_context(): void
    {
        $context = app(GymContext::class);
        $context->set(Gym::factory()->create());

        $context->clear();

        $this->assertFalse($context->has());
    }

    public function test_gym_without_context_throws(): void
    {
        $this->expectException(MissingGymContext::class);

        app(GymContext::class)->gym();
    }
}
