<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $t->foreignId('member_id')->constrained()->cascadeOnDelete();
            $t->foreignId('coach_id')->nullable()->constrained()->nullOnDelete();
            $t->string('domain', 20);
            $t->string('type', 20);
            $t->text('message')->nullable();
            $t->string('status', 20)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->text('review_note')->nullable();
            $t->timestamps();
            $t->index(['gym_id', 'status']);
            $t->index(['gym_id', 'member_id']);
            $t->index(['gym_id', 'coach_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_requests');
    }
};
