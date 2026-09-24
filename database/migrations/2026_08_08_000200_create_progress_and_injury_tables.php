<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('body_measurements', function (Blueprint $table) {
            $table->id(); $table->foreignId('gym_id')->constrained()->cascadeOnDelete(); $table->foreignId('member_id')->constrained()->cascadeOnDelete(); $table->foreignId('recorded_by')->constrained('users');
            $table->date('measured_on'); $table->decimal('weight_kg',6,2)->nullable(); $table->decimal('body_fat_percent',5,2)->nullable(); $table->decimal('waist_cm',6,2)->nullable(); $table->decimal('chest_cm',6,2)->nullable(); $table->decimal('arm_cm',6,2)->nullable(); $table->decimal('thigh_cm',6,2)->nullable(); $table->text('notes')->nullable(); $table->timestamps();
            $table->unique(['gym_id','member_id','measured_on']);
        });
        Schema::create('progress_photos', function (Blueprint $table) {
            $table->id(); $table->foreignId('gym_id')->constrained()->cascadeOnDelete(); $table->foreignId('member_id')->constrained()->cascadeOnDelete(); $table->foreignId('uploaded_by')->constrained('users');
            $table->date('captured_on'); $table->string('view_type',20); $table->string('path'); $table->string('mime_type',40); $table->unsignedBigInteger('size_bytes'); $table->string('visibility',20)->default('private'); $table->timestamp('consented_at'); $table->timestamps();
            $table->index(['gym_id','member_id','captured_on']);
        });
        Schema::create('injury_records', function (Blueprint $table) {
            $table->id(); $table->foreignId('gym_id')->constrained()->cascadeOnDelete(); $table->foreignId('member_id')->constrained()->cascadeOnDelete(); $table->foreignId('recorded_by')->constrained('users');
            $table->string('body_area',100); $table->string('title'); $table->string('severity',20); $table->date('occurred_on'); $table->date('expected_recovery_on')->nullable(); $table->text('restrictions'); $table->text('notes')->nullable(); $table->string('status',20)->default('active'); $table->boolean('training_coach_visibility')->default(false); $table->timestamp('consented_at')->nullable(); $table->timestamps();
            $table->index(['gym_id','member_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('injury_records'); Schema::dropIfExists('progress_photos'); Schema::dropIfExists('body_measurements'); }
};
