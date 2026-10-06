<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many minutes before a lesson starts a student may already be
     * scanned into it. Schools that already use the lesson attendance get
     * the default.
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('lesson_scan_early_minutes')->default(5)->after('lesson_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('lesson_scan_early_minutes');
        });
    }
};
