<?php

namespace Tests\Concerns;

use App\Core\Modules\ModuleRegistry;
use App\Core\Tenancy\GymContext;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Module;
use App\Models\User;

trait InteractsWithGym
{
    /**
     * Create a gym owned by a fresh platform user and attach the owner as an active owner member.
     *
     * @return array{0: Gym, 1: User}
     */
    protected function createGymWithOwner(array $gymAttributes = []): array
    {
        $owner = User::factory()->create();
        $gym = Gym::factory()->create(array_merge(['owner_id' => $owner->id], $gymAttributes));
        $this->attachUser($gym, $owner, 'owner');

        return [$gym, $owner];
    }

    protected function attachUser(Gym $gym, User $user, string $role = 'staff', array $extra = []): void
    {
        $gym->users()->attach($user, array_merge([
            'status' => 'active',
            'role' => $role,
            'joined_at' => now(),
        ], $extra));
    }

    /**
     * Enable the given modules for a gym, including transitive dependencies.
     */
    protected function enableModules(Gym $gym, array $keys): void
    {
        $registry = app(ModuleRegistry::class);
        $toEnable = [];

        foreach ($keys as $key) {
            $this->collectWithDependencies($registry, $key, $toEnable);
        }

        foreach ($toEnable as $key) {
            $definition = $registry->get($key);
            $module = Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'version' => $definition['version'],
                    'metadata_hash' => hash('sha256', json_encode($definition)),
                ],
            );
            $gym->modules()->syncWithoutDetaching([$module->id => ['enabled' => true, 'enabled_at' => now()]]);
        }
    }

    /** @param array<int, string> $acc */
    private function collectWithDependencies(ModuleRegistry $registry, string $key, array &$acc): void
    {
        if (in_array($key, $acc, true)) {
            return;
        }

        $acc[] = $key;

        foreach ($registry->get($key)['dependencies'] ?? [] as $dependency) {
            $this->collectWithDependencies($registry, $dependency, $acc);
        }
    }

    protected function createBranch(Gym $gym, array $attributes = []): Branch
    {
        $this->setGymContext($gym);

        return Branch::factory()->create($attributes);
    }

    /**
     * Create a gym with a branch, an active plan and a member, ready for commercial workflows.
     *
     * @param array<int, string> $modules modules to enable for the gym
     * @return array{gym: Gym, owner: User, branch: Branch, plan: MembershipPlan, member: Member}
     */
    protected function createCommercialFixture(array $modules = ['members', 'membership_plans', 'memberships', 'payments', 'attendance']): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, $modules);
        $this->setGymContext($gym);

        $branch = $this->createBranch($gym);
        $plan = MembershipPlan::factory()->create([
            'branch_id' => $branch->id,
            'duration_days' => 30,
            'price' => 100,
            'is_active' => true,
        ]);
        $member = Member::factory()->create(['branch_id' => $branch->id]);

        return ['gym' => $gym, 'owner' => $owner, 'branch' => $branch, 'plan' => $plan, 'member' => $member];
    }

    protected function setGymContext(Gym $gym): void
    {
        app(GymContext::class)->set($gym);
    }

    protected function actAsUserInGym(User $user, Gym $gym): void
    {
        $this->actingAs($user);
        $this->withSession(['current_gym_id' => $gym->getKey()]);
    }
}
