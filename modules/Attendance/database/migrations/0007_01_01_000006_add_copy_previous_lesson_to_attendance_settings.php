<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a teacher may copy the roll of the class's previous lesson of
     * the day into the one being filled. On for every school until the
     * admin switches it off.
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->boolean('lesson_copy_previous_enabled')->default(true)->after('lesson_scan_early_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('lesson_copy_previous_enabled');
        });
    }
};
