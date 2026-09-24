<?php

namespace App\Core\Modules;

use App\Core\Tenancy\GymContext;
use App\Models\Module;

class ModuleManager
{
    /** @var array<string, bool> */
    private array $resolved = [];

    public function __construct(
        private readonly GymContext $context,
        private readonly ModuleRegistry $registry,
    ) {}

    public function enabled(string $key): bool
    {
        if (! $this->registry->has($key) || ! $this->context->has()) {
            return false;
        }

        $cacheKey = $this->context->id().':'.$key;

        if (array_key_exists($cacheKey, $this->resolved)) {
            return $this->resolved[$cacheKey];
        }

        $definition = $this->registry->get($key);
        $dependenciesEnabled = collect($definition['dependencies'] ?? [])
            ->every(fn (string $dependency): bool => $this->enabled($dependency));

        return $this->resolved[$cacheKey] = $dependenciesEnabled && Module::query()
            ->where('key', $key)
            ->whereHas('gyms', fn ($query) => $query
                ->where('gyms.id', $this->context->id())
                ->where('gym_modules.enabled', true))
            ->exists();
    }

    public function flush(): void
    {
        $this->resolved = [];
    }
}
