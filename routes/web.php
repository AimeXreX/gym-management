<?php

use App\Core\Tenancy\GymAccess;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\GymSwitchController;
use App\Http\Controllers\HealthController;
use App\Modules\Commercial\Presentation\Http\BrandingController;
use App\Modules\Commercial\Presentation\Http\CoachAssignmentController;
use App\Modules\Commercial\Presentation\Http\CoachRequestController;
use App\Modules\Commercial\Presentation\Http\CommercialController;
use App\Modules\Commercial\Presentation\Http\DashboardController;
use App\Modules\Commercial\Presentation\Http\NotificationController;
use App\Modules\Commercial\Presentation\Http\ReleaseController;
use App\Modules\Commercial\Presentation\Http\WalletCafeController;
use App\Modules\Commercial\Presentation\Http\WellnessController;
use App\Modules\Commercial\Presentation\Http\WorkoutTemplateController;
use App\Modules\Gyms\Presentation\Http\PlatformDashboardController;
use App\Modules\Gyms\Presentation\Http\PlatformGymController;
use App\Modules\ModuleManagement\Presentation\Http\PlatformGymModulesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');
Route::get('/health', HealthController::class)->middleware('throttle:60,1')->name('health');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::name('account.')->group(function (): void {
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::post('/gyms/switch', GymSwitchController::class)->name('gyms.switch');
        Route::get('/gyms/select', function (Request $request, GymAccess $access) {
            return view('gyms.select', ['gyms' => $access->availableFor($request->user())]);
        })->name('gyms.select');
        Route::get('/no-gym', function (Request $request, GymAccess $access) {
            if ($access->availableFor($request->user())->isNotEmpty()) {
                return redirect()->route('account.gyms.select');
            }

            return view('gyms.no-gym');
        })->name('no-gym');
    });

    Route::prefix('platform')->name('platform.')->middleware('platform.admin')->group(function (): void {
        Route::get('/', PlatformDashboardController::class)->name('dashboard');
        Route::get('/gyms', [PlatformGymController::class, 'index'])->name('gyms.index');
        Route::get('/gyms/create', [PlatformGymController::class, 'create'])->name('gyms.create');
        Route::post('/gyms', [PlatformGymController::class, 'store'])->name('gyms.store');
        Route::get('/gyms/{gym}/edit', [PlatformGymController::class, 'edit'])->name('gyms.edit');
        Route::put('/gyms/{gym}', [PlatformGymController::class, 'update'])->name('gyms.update');
        Route::get('/gyms/{gym}/modules', [PlatformGymModulesController::class, 'edit'])->name('gyms.modules.edit');
        Route::put('/gyms/{gym}/modules', [PlatformGymModulesController::class, 'update'])->name('gyms.modules.update');
    });

    Route::name('tenant.')->middleware(['gym.context', 'tenant.area'])->group(function (): void {
        Route::get('/dashboard', DashboardController::class)
            ->middleware('module:dashboard')
            ->name('dashboard');

        Route::middleware('module:notifications')->group(function (): void {
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::get('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        });

        Route::middleware('module:members')->group(function (): void {
            Route::get('/members', [CommercialController::class, 'members'])->name('members.index');
            Route::get('/members/create', [CommercialController::class, 'createMember'])->name('members.create');
            Route::post('/members', [CommercialController::class, 'storeMember'])->middleware('gym.manage')->name('members.store');
            Route::get('/members/{member}', [CommercialController::class, 'showMember'])->name('members.show');
            Route::get('/members/{member}/edit', [CommercialController::class, 'editMember'])->name('members.edit');
            Route::put('/members/{member}', [CommercialController::class, 'updateMember'])->middleware('gym.manage')->name('members.update');
            Route::get('/members-export.csv', [ReleaseController::class, 'exportMembers'])->middleware('gym.manage')->name('members.export');
            Route::get('/members-import', [ReleaseController::class, 'importForm'])->middleware('gym.manage')->name('members.import');
            Route::get('/members-import/template.csv', [ReleaseController::class, 'importTemplate'])->middleware('gym.manage')->name('members.import.template');
            Route::post('/members-import/preview', [ReleaseController::class, 'previewImport'])->middleware('gym.manage')->name('members.import.preview');
            Route::post('/members-import/confirm', [ReleaseController::class, 'confirmImport'])->middleware('gym.manage')->name('members.import.confirm');
            Route::get('/members-import/errors.csv', [ReleaseController::class, 'importErrors'])->middleware('gym.manage')->name('members.import.errors');
            Route::post('/members/{member}/avatar', [ReleaseController::class, 'uploadMemberAvatar'])->middleware('gym.manage')->name('members.avatar');
            Route::get('/members/{member}/avatar', [ReleaseController::class, 'memberImage'])->name('members.avatar.show');
        });
        Route::post('/members/{member}/renew', [CommercialController::class, 'renew'])->middleware(['module:memberships', 'gym.manage'])->name('members.renew');
        Route::get('/members/{member}/qr-card', [CommercialController::class, 'qrCard'])->middleware('module:attendance')->name('members.qr-card');
        Route::post('/members/{member}/qr-token', [CommercialController::class, 'rotateToken'])->middleware(['module:attendance', 'gym.manage'])->name('members.qr-token');
        Route::get('/membership-plans', [CommercialController::class, 'plans'])->middleware('module:membership_plans')->name('plans.index');
        Route::post('/membership-plans', [CommercialController::class, 'storePlan'])->middleware(['module:membership_plans', 'gym.manage'])->name('plans.store');
        Route::put('/membership-plans/{plan}', [ReleaseController::class, 'updatePlan'])->middleware(['module:membership_plans', 'gym.manage'])->name('plans.update');
        Route::patch('/membership-plans/{plan}/status', [ReleaseController::class, 'planStatus'])->middleware(['module:membership_plans', 'gym.manage'])->name('plans.status');
        Route::get('/payments', [CommercialController::class, 'payments'])->middleware('module:payments')->name('payments.index');
        Route::post('/memberships/{membership}/payments', [CommercialController::class, 'storePayment'])->middleware(['module:payments', 'gym.manage'])->name('payments.store');
        Route::post('/payments/{payment}/void', [CommercialController::class, 'voidPayment'])->middleware(['module:payments', 'gym.manage'])->name('payments.void');
        Route::get('/attendance/check-in', [CommercialController::class, 'attendance'])->middleware('module:attendance')->name('attendance.index');
        Route::post('/attendance/check-in/{member}', [CommercialController::class, 'checkIn'])->middleware(['module:attendance', 'permission:attendance.checkin'])->name('attendance.check-in');
        Route::post('/attendance/{attendance}/check-out', [CommercialController::class, 'checkOut'])->middleware(['module:attendance', 'permission:attendance.checkin'])->name('attendance.check-out');
        Route::get('/branches', [CommercialController::class, 'branches'])->middleware('module:branches')->name('branches.index');
        Route::post('/branches', [CommercialController::class, 'storeBranch'])->middleware(['module:branches', 'gym.manage'])->name('branches.store');
        Route::put('/branches/{branch}', [ReleaseController::class, 'updateBranch'])->middleware(['module:branches', 'gym.manage'])->name('branches.update');
        Route::patch('/branches/{branch}/status', [ReleaseController::class, 'branchStatus'])->middleware(['module:branches', 'gym.manage'])->name('branches.status');
        Route::patch('/branches/{branch}/default', [ReleaseController::class, 'defaultBranch'])->middleware(['module:branches', 'gym.manage'])->name('branches.default');
        Route::get('/coaches', [CommercialController::class, 'coaches'])->middleware('module:coaches')->name('coaches.index');
        Route::post('/coaches', [CommercialController::class, 'storeCoach'])->middleware(['module:coaches', 'gym.manage'])->name('coaches.store');
        Route::get('/coaches/{coach}', [ReleaseController::class, 'coachDetail'])->middleware('module:coaches')->name('coaches.show');
        Route::put('/coaches/{coach}', [ReleaseController::class, 'updateCoach'])->middleware(['module:coaches', 'gym.manage'])->name('coaches.update');
        Route::patch('/coaches/{coach}/status', [ReleaseController::class, 'coachStatus'])->middleware(['module:coaches', 'gym.manage'])->name('coaches.status');
        Route::get('/coaches/{coach}/avatar', [ReleaseController::class, 'coachImage'])->middleware('module:coaches')->name('coaches.avatar');
        Route::get('/classes', [CommercialController::class, 'classes'])->middleware('module:classes')->name('classes.index');
        Route::post('/classes', [CommercialController::class, 'storeClass'])->middleware(['module:classes', 'gym.manage'])->name('classes.store');
        Route::get('/classes/{class}', [ReleaseController::class, 'classDetail'])->middleware('module:classes')->name('classes.show');
        Route::put('/classes/{class}', [ReleaseController::class, 'updateClass'])->middleware(['module:classes', 'gym.manage'])->name('classes.update');
        Route::patch('/classes/{class}/status', [ReleaseController::class, 'classStatus'])->middleware(['module:classes', 'gym.manage'])->name('classes.status');
        Route::patch('/class-sessions/{session}/cancel', [ReleaseController::class, 'cancelSession'])->middleware(['module:classes', 'gym.manage'])->name('classes.sessions.cancel');
        Route::patch('/class-enrollments/{enrollment}/remove', [ReleaseController::class, 'removeEnrollment'])->middleware(['module:classes', 'gym.manage'])->name('classes.enrollments.remove');
        Route::post('/class-sessions/{session}/enroll', [CommercialController::class, 'enroll'])->middleware(['module:classes', 'gym.manage'])->name('classes.enroll');
        Route::get('/reports', [ReleaseController::class, 'reports'])->middleware(['module:reports', 'permission:reports.view'])->name('reports.index');
        Route::get('/reports/details.csv', [ReleaseController::class, 'exportReport'])->middleware(['module:reports', 'permission:reports.view'])->name('reports.export');
        Route::get('/reports/payments.csv', [CommercialController::class, 'exportPayments'])->middleware(['module:reports', 'permission:reports.view'])->name('reports.payments.csv');
        Route::get('/activity', [CommercialController::class, 'activity'])->middleware('module:activity')->name('activity.index');
        Route::get('/search', [CommercialController::class, 'search'])->middleware('module:search')->name('search');

        Route::get('/settings', [ReleaseController::class, 'settings'])->middleware(['module:settings', 'gym.manage'])->name('settings');
        Route::put('/settings', [ReleaseController::class, 'updateSettings'])->middleware(['module:settings', 'gym.manage'])->name('settings.update');
        Route::get('/gym-logo', [ReleaseController::class, 'gymLogo'])->name('settings.logo');
        Route::get('/wallets', [WalletCafeController::class, 'wallets'])->middleware(['module:wallet', 'gym.manage'])->name('wallets.index');
        Route::get('/wallets/{member}', [WalletCafeController::class, 'wallet'])->middleware(['module:wallet', 'gym.manage'])->name('wallets.show');
        Route::post('/wallets/{member}/charge', [WalletCafeController::class, 'charge'])->middleware(['module:wallet', 'gym.manage'])->name('wallets.charge');
        Route::get('/cafe', [WalletCafeController::class, 'cafe'])->middleware(['module:cafe', 'gym.manage'])->name('cafe.index');
        Route::post('/cafe/categories', [WalletCafeController::class, 'storeCategory'])->middleware(['module:cafe', 'gym.manage'])->name('cafe.categories.store');
        Route::post('/cafe/products', [WalletCafeController::class, 'storeProduct'])->middleware(['module:cafe', 'gym.manage'])->name('cafe.products.store');
        Route::post('/cafe/checkout', [WalletCafeController::class, 'checkout'])->middleware(['module:cafe', 'gym.manage'])->name('cafe.checkout');
        Route::get('/appearance', [BrandingController::class, 'edit'])->middleware(['module:branding', 'gym.manage'])->name('branding.edit');
        Route::put('/appearance', [BrandingController::class, 'update'])->middleware(['module:branding', 'gym.manage'])->name('branding.update');
        Route::middleware('module:wellness')->group(function (): void {
            Route::get('/coach-dashboard', [WellnessController::class, 'dashboard'])->name('wellness.dashboard');
            Route::get('/wellness/{member?}', [WellnessController::class, 'index'])->name('wellness.index');
            Route::post('/wellness/{member}/food', [WellnessController::class, 'food'])->name('wellness.food');
            Route::post('/wellness/{member}/plans', [WellnessController::class, 'plan'])->name('wellness.plans');
            Route::post('/wellness/{member}/workouts', [WellnessController::class, 'workout'])->name('wellness.workouts');
            Route::post('/wellness/{member}/measurements', [WellnessController::class, 'measurement'])->name('wellness.measurements');
            Route::post('/wellness/{member}/photos', [WellnessController::class, 'photo'])->name('wellness.photos');
            Route::get('/wellness/photos/{photo}', [WellnessController::class, 'showPhoto'])->name('wellness.photos.show');
            Route::post('/wellness/{member}/injuries', [WellnessController::class, 'injury'])->name('wellness.injuries');
            Route::post('/wellness/{member}/check-in', [WellnessController::class, 'checkIn'])->name('wellness.check-in');
            Route::post('/wellness/{member}/check-out', [WellnessController::class, 'checkOut'])->name('wellness.check-out');
            Route::post('/wellness/{member}/programs', [WellnessController::class, 'workoutProgram'])->name('wellness.programs');
            Route::get('/wellness/programs/{program}/edit', [WellnessController::class, 'editWorkoutProgram'])->name('wellness.programs.edit');
            Route::put('/wellness/programs/{program}', [WellnessController::class, 'updateWorkoutProgram'])->name('wellness.programs.update');
            Route::patch('/wellness/programs/{program}/status', [WellnessController::class, 'workoutProgramStatus'])->name('wellness.programs.status');
            Route::post('/wellness/programs/{program}/feedback', [WellnessController::class, 'programFeedback'])->name('wellness.programs.feedback');
            Route::get('/wellness/plans/{plan}/edit', [WellnessController::class, 'editNutritionPlan'])->name('wellness.plans.edit');
            Route::put('/wellness/plans/{plan}', [WellnessController::class, 'updateNutritionPlan'])->name('wellness.plans.update');
            Route::get('/coach-profile', [WellnessController::class, 'profile'])->name('wellness.profile');
            Route::put('/coach-profile', [WellnessController::class, 'updateProfile'])->name('wellness.profile.update');
            Route::get('/exercises', [WorkoutTemplateController::class, 'exercises'])->name('wellness.exercises.index');
            Route::post('/exercises', [WorkoutTemplateController::class, 'storeExercise'])->name('wellness.exercises.store');
            Route::delete('/exercises/{exercise}', [WorkoutTemplateController::class, 'destroyExercise'])->name('wellness.exercises.destroy');
            Route::get('/workout-templates', [WorkoutTemplateController::class, 'index'])->name('wellness.templates.index');
            Route::post('/workout-templates', [WorkoutTemplateController::class, 'store'])->name('wellness.templates.store');
            Route::post('/workout-templates/{template}/assign', [WorkoutTemplateController::class, 'assign'])->name('wellness.templates.assign');
            Route::delete('/workout-templates/{template}', [WorkoutTemplateController::class, 'destroy'])->name('wellness.templates.destroy');
            Route::post('/wellness/{member}/coach-requests', [CoachRequestController::class, 'store'])->name('wellness.coach-requests.store');
            Route::get('/coach-requests', [WellnessController::class, 'requests'])->name('wellness.coach-requests.index');
            Route::post('/coach-requests/{coachRequest}/review', [CoachRequestController::class, 'review'])->name('wellness.coach-requests.review');
            Route::post('/members/{member}/coaches', [CoachAssignmentController::class, 'assign'])->middleware('gym.manage')->name('members.coaches.assign');
            Route::patch('/coach-assignments/{assignment}/default', [CoachAssignmentController::class, 'setDefault'])->middleware('gym.manage')->name('members.coaches.default');
            Route::delete('/coach-assignments/{assignment}', [CoachAssignmentController::class, 'unassign'])->middleware('gym.manage')->name('members.coaches.unassign');
        });
    });
});
