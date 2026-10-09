<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A school switches the printed static QR on or off. It starts off, so
     * no school gets a new way to scan without choosing it.
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->boolean('static_qr_enabled')->default(false)->after('lesson_copy_previous_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('static_qr_enabled');
        });
    }
};
