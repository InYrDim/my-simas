<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Central table for SaaS provider staff — NOT
     * tenant-scoped: no tenant_id, never BelongsToTenant. Provider
     * staff authenticate via the dedicated 'provider' guard and have
     * no access to tenant routes (and tenant users have none here).
     */
    public function up(): void
    {
        Schema::create('provider_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_users');
    }
};
