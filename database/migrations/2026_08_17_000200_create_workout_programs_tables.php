<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_programs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('member_id')->constrained()->cascadeOnDelete();
            $t->foreignId('coach_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->string('title');
            $t->date('starts_on');
            $t->date('ends_on')->nullable();
            $t->string('goal')->nullable();
            $t->string('status', 20)->default('draft');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['gym_id', 'member_id', 'status']);
            $t->index(['gym_id', 'coach_id']);
        });

        Schema::create('workout_program_days', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workout_program_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('day_number');
            $t->string('title')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['gym_id', 'workout_program_id', 'day_number']);
        });

        Schema::create('workout_program_sets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workout_program_day_id')->constrained()->cascadeOnDelete();
            $t->string('exercise_name');
            $t->unsignedSmallInteger('set_number');
            $t->decimal('weight_kg', 7, 2)->nullable();
            $t->unsignedSmallInteger('repetitions')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['gym_id', 'workout_program_day_id']);
        });

        Schema::table('workout_sessions', function (Blueprint $t) {
            $t->foreignId('program_id')->nullable()->after('gym_id')->constrained('workout_programs')->nullOnDelete();
            $t->foreignId('program_day_id')->nullable()->after('program_id')->constrained('workout_program_days')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $t) {
            $t->dropConstrainedForeignId('program_id');
            $t->dropConstrainedForeignId('program_day_id');
        });

        Schema::dropIfExists('workout_program_sets');
        Schema::dropIfExists('workout_program_days');
        Schema::dropIfExists('workout_programs');
    }
};
