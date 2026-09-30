<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Central table for school onboarding — NOT
     * tenant-scoped: an application exists BEFORE its tenant. The
     * applicant is not a user yet (users.tenant_id is NOT NULL), so
     * identity lives here as plain columns and no FK crosses into
     * Identity's users table. decided_by stays inside the module
     * (provider_users FK is legal).
     */
    public function up(): void
    {
        Schema::create('tenant_applications', function (Blueprint $table) {
            $table->id();
            $table->string('school_name');
            $table->string('desired_slug')->index();
            $table->string('timezone')->default('Asia/Jakarta');
            $table->string('applicant_name');
            $table->string('applicant_email')->index();
            $table->text('applicant_message')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('provider_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_applications');
    }
};
