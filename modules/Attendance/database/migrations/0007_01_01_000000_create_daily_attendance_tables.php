<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A school's attendance settings (one row) and the daily record of
     * each student: one row per student and day, holding the day's status
     * and the gate times. `student_id`, `class_id` and `recorded_by` are
     * plain ids of Core's and Identity's records; `class_id` is the class
     * the student sat in when the row was written.
     */
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->time('late_after')->default('07:00:00');
            $table->timestamps();

            $table->unique('tenant_id');
        });

        Schema::create('daily_attendances', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('class_id');
            $table->date('date');
            $table->string('status', 16);
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->string('check_in_method', 16)->nullable();
            $table->string('check_out_method', 16)->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id', 'date']);
            $table->index(['tenant_id', 'date', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_attendances');
        Schema::dropIfExists('attendance_settings');
    }
};
