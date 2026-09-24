<?php

namespace App\Core\Modules;

use InvalidArgumentException;

class ModuleRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $modules;

    public function __construct()
    {
        $definitions = config('modules.registry', []);
        $this->modules = collect($definitions)
            ->keyBy('key')
            ->all();

        if (count($this->modules) !== count($definitions)) {
            throw new InvalidArgumentException('Module keys must be unique.');
        }

        foreach ($this->modules as $key => $module) {
            foreach ($module['dependencies'] ?? [] as $dependency) {
                if (! isset($this->modules[$dependency])) {
                    throw new InvalidArgumentException("Module [{$key}] has unknown dependency [{$dependency}].");
                }
            }
        }

        foreach (array_keys($this->modules) as $key) {
            $this->assertAcyclic($key);
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->modules;
    }

    /** @return array<string, mixed> */
    public function get(string $key): array
    {
        return $this->modules[$key]
            ?? throw new InvalidArgumentException("Unknown module [{$key}].");
    }

    public function has(string $key): bool
    {
        return isset($this->modules[$key]);
    }

    /** @param array<string, bool> $visiting */
    private function assertAcyclic(string $key, array $visiting = []): void
    {
        if (isset($visiting[$key])) {
            throw new InvalidArgumentException("Circular module dependency detected at [{$key}].");
        }

        $visiting[$key] = true;

        foreach ($this->modules[$key]['dependencies'] ?? [] as $dependency) {
            $this->assertAcyclic($dependency, $visiting);
        }
    }
}
