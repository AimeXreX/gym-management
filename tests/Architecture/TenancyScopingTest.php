<?php

namespace Tests\Architecture;

use App\Core\Tenancy\Concerns\BelongsToGym;
use App\Models\Attendance;
use App\Models\BodyMeasurement;
use App\Models\Branch;
use App\Models\CafeCategory;
use App\Models\CafeOrder;
use App\Models\CafeOrderItem;
use App\Models\CafeProduct;
use App\Models\ClassEnrollment;
use App\Models\ClassSchedule;
use App\Models\ClassSession;
use App\Models\Coach;
use App\Models\CoachMemberAssignment;
use App\Models\FoodLog;
use App\Models\Gym;
use App\Models\GymClass;
use App\Models\InjuryRecord;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\Module;
use App\Models\NutritionPlan;
use App\Models\NutritionPlanItem;
use App\Models\Payment;
use App\Models\ProgressPhoto;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Tests\TestCase;

class TenancyScopingTest extends TestCase
{
    /** Every model that carries a gym_id column must be fail-closed scoped. */
    private const TENANT_MODELS = [
        Branch::class,
        Member::class,
        MembershipPlan::class,
        MemberMembership::class,
        Payment::class,
        Coach::class,
        GymClass::class,
        ClassSchedule::class,
        ClassSession::class,
        ClassEnrollment::class,
        Attendance::class,
        Wallet::class,
        WalletTransaction::class,
        CafeCategory::class,
        CafeProduct::class,
        CafeOrder::class,
        CafeOrderItem::class,
        FoodLog::class,
        NutritionPlan::class,
        NutritionPlanItem::class,
        WorkoutSession::class,
        WorkoutSet::class,
        BodyMeasurement::class,
        ProgressPhoto::class,
        InjuryRecord::class,
        CoachMemberAssignment::class,
    ];

    private const PLATFORM_MODELS = [
        Gym::class,
        User::class,
        Module::class,
    ];

    public function test_all_tenant_models_use_belongs_to_gym(): void
    {
        foreach (self::TENANT_MODELS as $model) {
            $this->assertTrue(
                in_array(BelongsToGym::class, class_uses_recursive($model), true),
                "{$model} must use BelongsToGym",
            );
        }
    }

    public function test_platform_models_are_not_tenant_scoped(): void
    {
        foreach (self::PLATFORM_MODELS as $model) {
            $this->assertFalse(
                in_array(BelongsToGym::class, class_uses_recursive($model), true),
                "{$model} must not use BelongsToGym",
            );
        }
    }
}
