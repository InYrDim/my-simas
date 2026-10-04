<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The lesson timetable: which subject a class has in which lesson slot
     * of the bell schedule. The teacher is not stored here; it is the one
     * the class teaching assignment names for that subject, so changing
     * the assignment moves the timetable with it. Relations are plain
     * indexed columns (no foreign keys but tenant_id).
     */
    public function up(): void
    {
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('period_slot_id')->index();
            $table->unsignedBigInteger('class_id')->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->unique(['tenant_id', 'period_slot_id', 'class_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
    }
};
