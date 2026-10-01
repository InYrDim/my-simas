<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Binds an application to the applicant account
     * that submitted it (plain indexed column — the architecture rules
     * allow no foreign key but tenant_id) and records the chosen plan.
     * All nullable: rows created before applicant accounts existed keep
     * working.
     */
    public function up(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            $table->unsignedBigInteger('applicant_id')->nullable()->unique()->after('id');
            $table->string('plan_key')->nullable()->after('timezone');
            $table->timestamp('submitted_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            $table->dropUnique(['applicant_id']);
            $table->dropColumn(['applicant_id', 'plan_key', 'submitted_at']);
        });
    }
};
