<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Tenant-scoped identities: the same email may exist in two
            // tenants. Fase 1 edits this migration directly (no data yet).
            $table->tenantId();

            $table->string('name');
            $table->string('email');
            $table->unique(['tenant_id', 'email']);
            $table->timestamp('email_verified_at')->nullable();
            // Nullable: invited users (and the provisioned first school
            // admin) only set a password via their set-password link.
            $table->string('password')->nullable();
            // Deactivation audit stamp (Fase 2): null = active. Login is
            // refused and live sessions are ended via attribute
            // enforcement — never via sessions.user_id lookups, which
            // are ambiguous across tenants (users.id repeats per tenant).
            $table->timestamp('deactivated_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Fase 2: tenant-aware reset/set-password tokens. The composite
        // (tenant_id, email) primary key keeps tokens scoped to one
        // school — a token minted in tenant A is useless on tenant B's
        // host even when the email matches.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->tenantId();
            $table->string('email');
            $table->primary(['tenant_id', 'email']);
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
