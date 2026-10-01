<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_profiles', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->unique('tenant_id');

            $table->string('level', 8)->default('sma');
            $table->string('npsn', 16)->nullable();
            $table->string('ownership', 16)->nullable();
            $table->string('accreditation', 32)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('headmaster')->nullable();
            $table->string('headmaster_nip', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->string('name', 16);
            $table->unique(['tenant_id', 'name']);
            $table->string('curriculum', 64);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 16)->default('draft');
            $table->timestamps();
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('academic_year_id');
            $table->string('name', 16);
            $table->unique(['academic_year_id', 'name']);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_years');
        Schema::dropIfExists('school_profiles');
    }
};
