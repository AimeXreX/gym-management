<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('category', 30)->default('strength');
            $t->string('muscle_group', 80)->nullable();
            $t->string('equipment', 80)->nullable();
            $t->text('description')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['gym_id', 'category']);
        });

        Schema::create('workout_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('coach_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('goal')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['gym_id', 'coach_id']);
        });

        Schema::create('workout_template_days', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workout_template_id')->constrained()->cascadeOnDelete();
            $t->string('phase', 80)->nullable();
            $t->unsignedSmallInteger('day_number');
            $t->string('title')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['gym_id', 'workout_template_id']);
        });

        Schema::create('workout_template_sets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workout_template_day_id')->constrained()->cascadeOnDelete();
            $t->foreignId('exercise_id')->nullable()->constrained()->nullOnDelete();
            $t->string('exercise_name');
            $t->unsignedSmallInteger('set_number');
            $t->decimal('weight_kg', 7, 2)->nullable();
            $t->unsignedSmallInteger('repetitions')->nullable();
            $t->unsignedSmallInteger('duration_seconds')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['gym_id', 'workout_template_day_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_template_sets');
        Schema::dropIfExists('workout_template_days');
        Schema::dropIfExists('workout_templates');
        Schema::dropIfExists('exercises');
    }
};
