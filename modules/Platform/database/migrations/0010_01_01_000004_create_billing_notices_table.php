<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per billing message sent (or refused) to a school's billing
     * contact. subscription_id and invoice_id are plain indexed columns
     * (no FK). The unique index records a scheduled message once per
     * subscription, kind and anchor date; rows without an anchor date
     * (manual resends, one-off messages) never collide.
     */
    public function up(): void
    {
        Schema::create('billing_notices', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->string('kind', 40);
            $table->string('channel', 20)->default('email');
            $table->string('recipient')->nullable();
            $table->string('status', 20)->index();
            $table->date('anchor_date')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'kind', 'anchor_date'], 'billing_notices_scheduled_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_notices');
    }
};
