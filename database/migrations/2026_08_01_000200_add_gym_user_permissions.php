<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_user', fn (Blueprint $table) => $table->json('permissions_json')->nullable()->after('role'));
    }

    public function down(): void
    {
        Schema::table('gym_user', fn (Blueprint $table) => $table->dropColumn('permissions_json'));
    }
};
