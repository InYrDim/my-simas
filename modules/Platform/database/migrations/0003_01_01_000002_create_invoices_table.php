<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invoices. plan_name / billing_cycle / amount are snapshots taken at
     * issue time, so later plan edits never rewrite history. subscription_id
     * and plan_id are plain indexed columns (no FK).
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->tenantId();
            $table->unsignedBigInteger('subscription_id')->index();
            $table->unsignedBigInteger('plan_id')->index();
            $table->string('plan_name');
            $table->string('billing_cycle');
            $table->unsignedBigInteger('amount');
            $table->string('status')->index();
            $table->date('issued_at');
            $table->date('due_at');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
