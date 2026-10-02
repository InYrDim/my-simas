<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Applicants' own accounts — CENTRAL, not tenant-scoped (no
     * BelongsToTenant): an applicant exists before they join a school.
     * `tenant_id` is the one school the account has joined (empty until
     * then); it is the sole foreign key, as for every table. The account
     * keeps only who the applicant is and where they applied — everything
     * about the application lives in the school's own tables.
     */
    public function up(): void
    {
        Schema::create('ppdb_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->foreignUlid('tenant_id')->nullable()->constrained('tenants');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppdb_accounts');
    }
};
