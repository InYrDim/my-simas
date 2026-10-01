<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relations between these tables are plain indexed columns: the
     * architecture rules allow no database foreign key except tenant_id.
     */
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->string('name', 8);
            $table->unique(['tenant_id', 'name']);
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
        });

        Schema::create('majors', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->string('code', 16);
            $table->unique(['tenant_id', 'code']);
            $table->string('name');
            $table->string('kind', 32);
            $table->json('concentrations')->nullable();
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->string('code', 16);
            $table->unique(['tenant_id', 'code']);
            $table->string('name');
            $table->string('type', 32);
            $table->unsignedSmallInteger('capacity');
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->string('code', 16);
            $table->unique(['tenant_id', 'code']);
            $table->string('name');
            $table->string('group', 32);
            $table->unsignedTinyInteger('kkm')->default(75);
            $table->timestamps();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('academic_year_id')->index();
            $table->unsignedBigInteger('grade_id')->index();
            $table->unsignedBigInteger('major_id')->nullable()->index();
            $table->unsignedBigInteger('room_id')->nullable()->index();
            // Plain id of a Core teacher; set from Akademik › Wali Kelas.
            $table->unsignedBigInteger('homeroom_teacher_id')->nullable()->index();

            $table->string('name', 32);
            $table->unique(['tenant_id', 'academic_year_id', 'name']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('majors');
        Schema::dropIfExists('grades');
    }
};
