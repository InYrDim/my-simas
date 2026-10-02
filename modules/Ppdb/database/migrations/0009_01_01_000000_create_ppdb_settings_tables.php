<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A school's admissions setup: the periods (one is active), the waves
     * inside a period (open and close dates) and the admission paths with
     * their quota. Relations are plain indexed ids — no foreign keys but
     * tenant_id. Dates are kept as `Y-m-d` days of the school.
     */
    public function up(): void
    {
        Schema::create('ppdb_periods', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->string('name');
            $table->unsignedSmallInteger('entry_year');
            $table->string('status', 16)->default('draft');
            $table->timestamp('results_published_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('ppdb_waves', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('period_id');
            $table->string('name');
            $table->date('opens_on');
            $table->date('closes_on');
            $table->timestamps();

            $table->index(['tenant_id', 'period_id']);
        });

        Schema::create('ppdb_paths', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('period_id');
            $table->string('name', 64);
            $table->unsignedInteger('quota')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'period_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppdb_paths');
        Schema::dropIfExists('ppdb_waves');
        Schema::dropIfExists('ppdb_periods');
    }
};
