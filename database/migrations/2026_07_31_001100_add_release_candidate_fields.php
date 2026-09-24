<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->text('void_reason')->nullable()->after('voided_by'));
        Schema::table('class_sessions', fn (Blueprint $t) => $t->unique(['gym_id', 'class_schedule_id', 'starts_at'], 'class_sessions_occurrence_unique'));
    }

    public function down(): void
    {
        Schema::table('class_sessions', fn (Blueprint $t) => $t->dropUnique('class_sessions_occurrence_unique'));
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('void_reason'));
    }
};
