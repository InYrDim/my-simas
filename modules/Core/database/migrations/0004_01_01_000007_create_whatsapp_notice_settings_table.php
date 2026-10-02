<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a school decided about a kind of WhatsApp notice: on or off,
     * and its own wording (`template` null = the kind's default). A kind
     * without a row is off.
     */
    public function up(): void
    {
        Schema::create('whatsapp_notice_settings', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->string('kind', 64);
            $table->boolean('enabled')->default(false);
            $table->text('template')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notice_settings');
    }
};
