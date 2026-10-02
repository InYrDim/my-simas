<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One WhatsApp instance per tenant: the request a school made, the
     * provider's decision, and the OpenWA session behind it. `api_key`
     * holds the session-scoped key, encrypted with OPENWA_CREDENTIALS_KEY.
     * requested_by / decided_by are plain columns (a school user id, a
     * provider user id): the only foreign key is tenant_id → tenants.
     */
    public function up(): void
    {
        Schema::create('whatsapp_instances', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->unique('tenant_id');

            $table->string('status', 16)->index();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('note')->nullable();

            $table->string('session_id', 64)->nullable();
            $table->string('session_name', 64)->nullable();
            $table->text('api_key')->nullable();
            $table->string('api_key_id', 64)->nullable();

            $table->string('connection_status', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('push_name')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_instances');
    }
};
