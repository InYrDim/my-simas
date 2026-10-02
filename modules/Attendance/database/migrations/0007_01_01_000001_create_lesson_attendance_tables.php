<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance per lesson: a session is one class in one lesson slot of
     * one day (the slot's times are copied, so a later change of the bell
     * schedule leaves the record as it was), and each session holds one
     * row per student. All ids are plain columns.
     */
    public function up(): void
    {
        Schema::create('lesson_sessions', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('class_id');
            $table->date('date');
            $table->unsignedBigInteger('period_slot_id');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'class_id', 'date', 'period_slot_id']);
        });

        Schema::create('lesson_attendances', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('lesson_session_id');
            $table->unsignedBigInteger('student_id');
            $table->string('status', 16);
            $table->string('method', 16);
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['lesson_session_id', 'student_id']);
            $table->index(['tenant_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_attendances');
        Schema::dropIfExists('lesson_sessions');
    }
};
