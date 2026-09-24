<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_program_feedback', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workout_program_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();
            $t->index(['gym_id', 'workout_program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_program_feedback');
    }
};
