<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A school switches the gate and the lesson attendance on or off. Both
     * start on, so schools that already use them notice nothing.
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->boolean('gate_enabled')->default(true)->after('late_after');
            $table->boolean('lesson_enabled')->default(true)->after('gate_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn(['gate_enabled', 'lesson_enabled']);
        });
    }
};
