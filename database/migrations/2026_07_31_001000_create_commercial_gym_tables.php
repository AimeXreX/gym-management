<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_user', fn (Blueprint $t) => $t->string('role', 20)->default('staff')->after('status')->index());
        Schema::table('branches', function (Blueprint $t) {
            $t->string('phone', 30)->nullable();
            $t->string('manager_name')->nullable();
            $t->string('manager_mobile', 30)->nullable();
            $t->text('address')->nullable();
            $t->json('operating_hours')->nullable();
        });
        Schema::create('members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('membership_code', 40);
            $t->string('public_token', 64)->unique();
            $t->string('first_name', 100);
            $t->string('last_name', 100);
            $t->string('mobile', 30);
            $t->string('secondary_mobile', 30)->nullable();
            $t->string('email')->nullable();
            $t->string('national_id', 30)->nullable();
            $t->date('birth_date')->nullable();
            $t->string('gender', 20)->nullable();
            $t->string('avatar_path')->nullable();
            $t->string('emergency_contact_name')->nullable();
            $t->string('emergency_contact_mobile', 30)->nullable();
            $t->decimal('height_cm', 5, 2)->nullable();
            $t->decimal('weight_kg', 6, 2)->nullable();
            $t->text('medical_notes')->nullable();
            $t->text('notes')->nullable();
            $t->string('status', 20)->default('active');
            $t->date('joined_at');
            $t->timestamp('last_attended_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['gym_id', 'membership_code']);
            $t->index(['gym_id', 'status']);
            $t->index(['gym_id', 'mobile']);
            $t->index(['gym_id', 'branch_id']);
        });
        Schema::create('membership_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->unsignedInteger('duration_days');
            $t->unsignedInteger('session_limit')->nullable();
            $t->decimal('price', 14, 2);
            $t->string('currency', 3)->default('IRR');
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['gym_id', 'is_active']);
            $t->index(['gym_id', 'branch_id']);
        });
        Schema::create('member_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('member_id')->constrained()->restrictOnDelete();
            $t->foreignId('membership_plan_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('reference', 50);
            $t->date('starts_at');
            $t->date('ends_at');
            $t->string('status', 20)->default('active');
            $t->decimal('agreed_price', 14, 2);
            $t->decimal('discount_amount', 14, 2)->default(0);
            $t->decimal('payable_amount', 14, 2);
            $t->decimal('paid_amount', 14, 2)->default(0);
            $t->unsignedInteger('session_limit')->nullable();
            $t->unsignedInteger('sessions_used')->default(0);
            $t->timestamp('frozen_at')->nullable();
            $t->timestamp('freeze_ends_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['gym_id', 'reference']);
            $t->index(['gym_id', 'member_id', 'status']);
            $t->index(['gym_id', 'status', 'ends_at']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('member_id')->constrained()->restrictOnDelete();
            $t->foreignId('membership_id')->nullable()->constrained('member_memberships')->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->decimal('amount', 14, 2);
            $t->string('payment_method', 20);
            $t->string('reference_number', 100)->nullable();
            $t->timestamp('paid_at');
            $t->string('status', 20)->default('completed');
            $t->text('notes')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('voided_at')->nullable();
            $t->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['gym_id', 'paid_at']);
            $t->index(['gym_id', 'status']);
        });
        Schema::create('coaches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('first_name', 100);
            $t->string('last_name', 100);
            $t->string('mobile', 30);
            $t->string('email')->nullable();
            $t->string('avatar_path')->nullable();
            $t->string('specialty')->nullable();
            $t->text('biography')->nullable();
            $t->date('hire_date')->nullable();
            $t->string('status', 20)->default('active');
            $t->text('compensation_notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['gym_id', 'status']);
            $t->index(['gym_id', 'branch_id']);
        });
        Schema::create('gym_classes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('coach_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->unsignedSmallInteger('capacity');
            $t->unsignedSmallInteger('duration_minutes');
            $t->string('level', 30)->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->softDeletes();
            $t->index(['gym_id', 'status']);
            $t->index(['gym_id', 'branch_id']);
        });
        Schema::create('class_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('gym_class_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('weekday');
            $t->time('starts_at');
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->index(['gym_id', 'weekday', 'status']);
        });
        Schema::create('class_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('gym_class_id')->constrained()->restrictOnDelete();
            $t->foreignId('class_schedule_id')->nullable()->constrained()->nullOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('status', 20)->default('scheduled');
            $t->timestamps();
            $t->index(['gym_id', 'starts_at', 'status']);
        });
        Schema::create('class_enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('class_session_id')->constrained()->restrictOnDelete();
            $t->foreignId('member_id')->constrained()->restrictOnDelete();
            $t->string('status', 20)->default('enrolled');
            $t->timestamp('enrolled_at');
            $t->timestamps();
            $t->unique(['class_session_id', 'member_id']);
            $t->index(['gym_id', 'member_id', 'status']);
        });
        Schema::create('attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('member_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('membership_id')->nullable()->constrained('member_memberships')->nullOnDelete();
            $t->foreignId('class_session_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('checked_in_at');
            $t->timestamp('checked_out_at')->nullable();
            $t->string('method', 20);
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['gym_id', 'checked_in_at']);
            $t->index(['gym_id', 'member_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        foreach (['attendances', 'class_enrollments', 'class_sessions', 'class_schedules', 'gym_classes', 'coaches', 'payments', 'member_memberships', 'membership_plans', 'members'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('branches', fn (Blueprint $t) => $t->dropColumn(['phone', 'manager_name', 'manager_mobile', 'address', 'operating_hours']));
        Schema::table('gym_user', fn (Blueprint $t) => $t->dropColumn('role'));
    }
};
