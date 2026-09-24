<?php

namespace Tests\Unit;

use App\Core\Modules\ModuleRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class ModuleRegistryTest extends TestCase
{
    public function test_real_config_registry_is_valid(): void
    {
        $registry = new ModuleRegistry();

        $this->assertNotEmpty($registry->all());
        $this->assertTrue($registry->has('members'));
        $this->assertFalse($registry->has('does-not-exist'));
    }

    public function test_duplicate_keys_are_rejected(): void
    {
        config()->set('modules.registry', [
            ['key' => 'a', 'name' => 'A'],
            ['key' => 'a', 'name' => 'B'],
        ]);

        $this->expectException(InvalidArgumentException::class);

        new ModuleRegistry();
    }

    public function test_unknown_dependency_is_rejected(): void
    {
        config()->set('modules.registry', [
            ['key' => 'a', 'name' => 'A', 'dependencies' => ['missing']],
        ]);

        $this->expectException(InvalidArgumentException::class);

        new ModuleRegistry();
    }

    public function test_circular_dependency_is_rejected(): void
    {
        config()->set('modules.registry', [
            ['key' => 'a', 'name' => 'A', 'dependencies' => ['b']],
            ['key' => 'b', 'name' => 'B', 'dependencies' => ['a']],
        ]);

        $this->expectException(InvalidArgumentException::class);

        new ModuleRegistry();
    }

    public function test_unknown_module_get_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ModuleRegistry())->get('unknown');
    }
}
