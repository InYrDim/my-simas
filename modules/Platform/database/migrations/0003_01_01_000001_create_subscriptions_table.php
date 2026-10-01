<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One subscription per tenant. plan_id is a plain indexed column (no
     * FK): the only legal foreign key is tenant_id → tenants.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->unsignedBigInteger('plan_id')->index();
            $table->string('billing_cycle')->default('monthly');
            $table->string('status')->index();
            $table->date('trial_ends_at')->nullable();
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
