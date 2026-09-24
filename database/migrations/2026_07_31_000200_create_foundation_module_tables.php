<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('version', 30)->default('1.0.0');
            $table->string('metadata_hash', 64);
            $table->timestamps();
        });

        Schema::create('gym_modules', function (Blueprint $table) {
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->json('config_json')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
            $table->primary(['gym_id', 'module_id']);
            $table->index(['gym_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_modules');
        Schema::dropIfExists('modules');
    }
};
