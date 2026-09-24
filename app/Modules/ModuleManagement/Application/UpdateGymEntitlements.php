<?php

namespace App\Modules\ModuleManagement\Application;

use App\Core\Modules\ModuleManager;
use App\Core\Modules\ModuleRegistry;
use App\Models\Gym;
use App\Models\Module;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateGymEntitlements
{
    private const REQUIRED_MODULES = ['gyms', 'dashboard', 'settings'];

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleManager $manager,
    ) {}

    /** @return array{before: array<int, string>, after: array<int, string>} */
    public function handle(Gym $gym, array $requestedKeys): array
    {
        $requestedKeys = array_values(array_unique(array_merge($requestedKeys, self::REQUIRED_MODULES)));
        $unknown = array_diff($requestedKeys, array_keys($this->registry->all()));

        if ($unknown !== []) {
            throw ValidationException::withMessages(['modules' => __('ui.modules_invalid')]);
        }

        foreach ($requestedKeys as $key) {
            $missing = array_diff($this->registry->get($key)['dependencies'] ?? [], $requestedKeys);
            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'modules' => __('ui.module_dependency_missing', [
                        'module' => $this->registry->get($key)['name'],
                        'dependency' => implode(', ', $missing),
                    ]),
                ]);
            }
        }

        return DB::transaction(function () use ($gym, $requestedKeys): array {
            $before = $gym->modules()->wherePivot('enabled', true)->pluck('modules.key')->all();
            $modules = Module::query()->whereIn('key', array_keys($this->registry->all()))->get()->keyBy('key');

            if ($modules->count() !== count($this->registry->all())) {
                throw ValidationException::withMessages(['modules' => __('ui.module_registry_not_synced')]);
            }

            $existing = $gym->modules()->get()->keyBy('key');
            $sync = [];
            foreach ($modules as $key => $module) {
                $enabled = in_array($key, $requestedKeys, true);
                $sync[$module->id] = [
                    'enabled' => $enabled,
                    'enabled_at' => $enabled
                        ? ($existing->get($key)?->pivot->enabled_at ?? now())
                        : null,
                    'config_json' => $existing->get($key)?->pivot->config_json,
                ];
            }

            $gym->modules()->sync($sync);
            $this->manager->flush();

            return ['before' => $before, 'after' => $requestedKeys];
        });
    }
}
