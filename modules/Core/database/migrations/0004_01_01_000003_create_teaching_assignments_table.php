<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who teaches which subject in which class. The class already belongs
     * to one academic year, so an assignment needs no period of its own.
     * Relations are plain indexed columns (no foreign keys but tenant_id).
     */
    public function up(): void
    {
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('class_id')->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->unsignedBigInteger('teacher_id')->index();
            $table->unique(['tenant_id', 'class_id', 'subject_id']);
            $table->unsignedTinyInteger('hours_per_week')->default(2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
    }
};
