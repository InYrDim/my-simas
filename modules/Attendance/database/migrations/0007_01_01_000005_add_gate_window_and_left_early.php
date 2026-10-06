<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The hours the gate takes scans (loose defaults, so schools that
     * already use the gate notice nothing) and whether a student left
     * before the last lesson of the day was over.
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->time('gate_opens_at')->default('05:00:00')->after('lesson_scan_early_minutes');
            $table->time('gate_closes_at')->default('18:00:00')->after('gate_opens_at');
        });

        Schema::table('daily_attendances', function (Blueprint $table) {
            $table->boolean('left_early')->default(false)->after('check_out_method');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn(['gate_opens_at', 'gate_closes_at']);
        });

        Schema::table('daily_attendances', function (Blueprint $table) {
            $table->dropColumn('left_early');
        });
    }
};
