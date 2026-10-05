<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A teacher's own "done" mark for one lesson of one day: the todo
     * checkbox on Jadwal Hari Ini. Written when the teacher ticks the
     * card after the lesson hour, and touched when they save the lesson
     * attendance. `user_id` is the teacher's login account (a plain
     * column; Identity's table is never joined).
     */
    public function up(): void
    {
        Schema::create('lesson_checks', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('class_id');
            $table->date('date');
            $table->unsignedBigInteger('period_slot_id');
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'date', 'period_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_checks');
    }
};
