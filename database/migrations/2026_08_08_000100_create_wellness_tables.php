<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) { $table->foreignId('user_id')->nullable()->after('gym_id')->constrained()->nullOnDelete(); $table->unique(['gym_id', 'user_id']); });
        Schema::table('coaches', function (Blueprint $table) { $table->foreignId('user_id')->nullable()->after('gym_id')->constrained()->nullOnDelete(); $table->string('coach_type', 20)->default('training')->after('specialty'); $table->unique(['gym_id', 'user_id']); });
        Schema::create('coach_member_assignments', function (Blueprint $table) { $table->id(); $table->foreignId('gym_id')->constrained()->cascadeOnDelete(); $table->foreignId('coach_id')->constrained()->cascadeOnDelete(); $table->foreignId('member_id')->constrained()->cascadeOnDelete(); $table->string('domain', 20); $table->boolean('is_active')->default(true); $table->timestamps(); $table->unique(['gym_id','coach_id','member_id','domain'], 'coach_member_domain_unique'); $table->index(['gym_id','member_id','is_active']); });
        Schema::create('nutrition_plans', function (Blueprint $table) { $table->id(); $table->foreignId('gym_id')->constrained()->cascadeOnDelete(); $table->foreignId('member_id')->constrained()->cascadeOnDelete(); $table->foreignId('created_by')->constrained('users'); $table->string('title'); $table->date('starts_on'); $table->date('ends_on')->nullable(); $table->string('goal')->nullable(); $table->text('notes')->nullable(); $table->string('status',20)->default('active'); $table->timestamps(); $table->index(['gym_id','member_id','status']); });
        Schema::create('nutrition_plan_items', function (Blueprint $table) { $table->id(); $table->foreignId('gym_id')->constrained()->cascadeOnDelete(); $table->foreignId('nutrition_plan_id')->constrained()->cascadeOnDelete(); $table->string('meal_name',80); $table->time('suggested_at')->nullable(); $table->text('foods'); $table->unsignedSmallInteger('sort_order')->default(0); $table->timestamps(); });
        Schema::create('food_logs', function (Blueprint $table) { $table->id(); $table->foreignId('gym_id')->constrained()->cascadeOnDelete(); $table->foreignId('member_id')->constrained()->cascadeOnDelete(); $table->dateTime('consumed_at'); $table->string('meal_name',80)->nullable(); $table->text('foods'); $table->text('notes')->nullable(); $table->boolean('coach_visibility')->default(false); $table->timestamps(); $table->index(['gym_id','member_id','consumed_at']); });
        Schema::create('workout_sessions', function (Blueprint $table) { $table->id(); $table->foreignId('gym_id')->constrained()->cascadeOnDelete(); $table->foreignId('member_id')->constrained()->cascadeOnDelete(); $table->foreignId('recorded_by')->constrained('users'); $table->date('performed_on'); $table->string('title')->nullable(); $table->text('notes')->nullable(); $table->timestamps(); $table->index(['gym_id','member_id','performed_on']); });
        Schema::create('workout_sets', function (Blueprint $table) { $table->id(); $table->foreignId('gym_id')->constrained()->cascadeOnDelete(); $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete(); $table->string('exercise_name'); $table->unsignedSmallInteger('set_number'); $table->decimal('weight_kg',7,2)->nullable(); $table->unsignedSmallInteger('repetitions')->nullable(); $table->unsignedSmallInteger('duration_seconds')->nullable(); $table->text('notes')->nullable(); $table->timestamps(); });
    }
    public function down(): void
    {
        Schema::dropIfExists('workout_sets'); Schema::dropIfExists('workout_sessions'); Schema::dropIfExists('food_logs'); Schema::dropIfExists('nutrition_plan_items'); Schema::dropIfExists('nutrition_plans'); Schema::dropIfExists('coach_member_assignments');
        Schema::table('coaches', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        Schema::table('members', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
    }
};
