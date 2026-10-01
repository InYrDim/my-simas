<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `user_id` is a plain column holding an Identity user id (no foreign
     * key, no relation): other modules never import Identity's User model,
     * and the architecture rules allow no foreign key but tenant_id.
     */
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->string('name');
            $table->string('nip', 32)->nullable();
            $table->string('nuptk', 32)->nullable();
            $table->string('employment', 16);
            $table->string('duty', 32);
            $table->string('email')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'nip']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->string('name');
            $table->string('nis', 32);
            $table->unique(['tenant_id', 'nis']);
            $table->string('nisn', 32)->nullable();
            $table->unique(['tenant_id', 'nisn']);
            $table->string('gender', 1);
            $table->date('birth_date')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone', 32)->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('class_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('student_class_history', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('academic_year_id');
            $table->unique(['student_id', 'academic_year_id']);
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('class_name', 32);
            $table->string('note');
            $table->timestamps();
        });

        Schema::create('extracurriculars', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->string('name');
            $table->unsignedBigInteger('coach_teacher_id')->nullable()->index();
            $table->string('schedule')->nullable();
            $table->string('kind', 16);
            $table->timestamps();
        });

        Schema::create('extracurricular_members', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('extracurricular_id');
            $table->unsignedBigInteger('student_id')->index();
            $table->unique(['extracurricular_id', 'student_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extracurricular_members');
        Schema::dropIfExists('extracurriculars');
        Schema::dropIfExists('student_class_history');
        Schema::dropIfExists('students');
        Schema::dropIfExists('teachers');
    }
};
