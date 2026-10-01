<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Central table for people applying to bring a
     * school onto the platform — NOT tenant-scoped: an applicant exists
     * before their tenant does (users.tenant_id is NOT NULL, so they
     * cannot be a school user yet). `tenant_id` is filled at approval
     * and points at the school the applicant then belongs to.
     *
     * `password` is nullable: a provider-invited applicant has none until
     * they accept the invitation, and it is cleared once it has been
     * handed to the school admin account at approval.
     */
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->foreignUlid('tenant_id')->nullable()->constrained('tenants');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
