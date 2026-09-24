<?php

namespace Database\Seeders;

use App\Core\Modules\ModuleRegistry;
use App\Core\Tenancy\GymContext;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\CoachMemberAssignment;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') && ! config('demo.allow_test_accounts')) {
            $this->command?->warn('Test accounts are disabled. Set ALLOW_TEST_ACCOUNTS=true explicitly to create them outside local.');

            return;
        }

        $accounts = [
            'admin@gym.test' => ['مدیر پلتفرم', null, true],
            'demo@gym.test' => ['مالک باشگاه', 'owner', false],
            'manager@gym.test' => ['مدیر باشگاه', 'manager', false],
            'staff@gym.test' => ['پذیرش', 'staff', false],
            'accountant@gym.test' => ['حسابدار', 'accountant', false],
            'coach@gym.test' => ['مربی تمرین و تغذیه', 'coach', false],
            'nutrition@gym.test' => ['مربی تغذیه', 'nutrition_coach', false],
            'member@gym.test' => ['ورزشکار', 'member', false],
        ];
        $users = collect($accounts)->mapWithKeys(function (array $account, string $email) {
            [$name, $role, $platform] = $account;
            $user = User::query()->updateOrCreate(['email' => $email], ['name' => $name, 'password' => 'password', 'locale' => 'fa', 'is_platform_admin' => $platform]);

            return [$role ?? 'platform_admin' => $user];
        });

        $gym = Gym::query()->updateOrCreate(['slug' => 'demo-gym'], ['name' => 'باشگاه تست نقش‌ها', 'owner_id' => $users['owner']->id, 'status' => 'active', 'timezone' => 'Asia/Tehran', 'locale' => 'fa', 'currency' => 'IRR']);
        foreach (['owner', 'manager', 'staff', 'accountant', 'coach', 'nutrition_coach', 'member'] as $role) {
            $gym->users()->syncWithoutDetaching([$users[$role]->id => ['status' => 'active', 'role' => $role, 'joined_at' => now()]]);
        }
        app(GymContext::class)->set($gym);
        $branch = Branch::query()->firstOrCreate(['is_system_default' => true], ['name' => 'شعبه اصلی', 'status' => 'active']);

        foreach (app(ModuleRegistry::class)->all() as $definition) {
            $module = Module::query()->updateOrCreate(['key' => $definition['key']], ['name' => $definition['name'], 'description' => $definition['description'], 'version' => $definition['version'], 'metadata_hash' => hash('sha256', json_encode($definition))]);
            $gym->modules()->syncWithoutDetaching([$module->id => ['enabled' => true, 'enabled_at' => now()]]);
        }

        $plan = MembershipPlan::query()->firstOrCreate(['name' => 'عضویت تست'], ['branch_id' => $branch->id, 'duration_days' => 365, 'session_limit' => null, 'price' => 0, 'currency' => 'IRR', 'is_active' => true]);
        $member = Member::query()->updateOrCreate(['membership_code' => 'TEST-MEMBER'], ['user_id' => $users['member']->id, 'branch_id' => $branch->id, 'public_token' => Str::random(48), 'first_name' => 'ورزشکار', 'last_name' => 'تست', 'mobile' => '09120000001', 'status' => 'active', 'joined_at' => today(), 'created_by' => $users['owner']->id]);
        MemberMembership::query()->firstOrCreate(['member_id' => $member->id, 'reference' => 'TEST-MEMBERSHIP'], ['membership_plan_id' => $plan->id, 'branch_id' => $branch->id, 'starts_at' => today(), 'ends_at' => today()->addYear(), 'status' => 'active', 'agreed_price' => 0, 'discount_amount' => 0, 'payable_amount' => 0, 'paid_amount' => 0, 'created_by' => $users['owner']->id]);
        $training = Coach::query()->updateOrCreate(['mobile' => '09120000002'], ['user_id' => $users['coach']->id, 'branch_id' => $branch->id, 'first_name' => 'مربی', 'last_name' => 'دوگانه', 'specialty' => 'تمرین و تغذیه ورزشی', 'coach_type' => 'both', 'status' => 'active']);
        $nutrition = Coach::query()->updateOrCreate(['mobile' => '09120000003'], ['user_id' => $users['nutrition_coach']->id, 'branch_id' => $branch->id, 'first_name' => 'مربی', 'last_name' => 'تغذیه', 'specialty' => 'تغذیه ورزشی', 'coach_type' => 'nutrition', 'status' => 'active']);
        CoachMemberAssignment::query()->firstOrCreate(['coach_id' => $training->id, 'member_id' => $member->id, 'domain' => 'training'], ['is_active' => true]);
        CoachMemberAssignment::query()->firstOrCreate(['coach_id' => $training->id, 'member_id' => $member->id, 'domain' => 'nutrition'], ['is_active' => true]);
        CoachMemberAssignment::query()->firstOrCreate(['coach_id' => $nutrition->id, 'member_id' => $member->id, 'domain' => 'nutrition'], ['is_active' => true]);
    }
}
