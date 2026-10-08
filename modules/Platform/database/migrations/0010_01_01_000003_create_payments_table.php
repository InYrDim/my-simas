<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment attempts against an invoice. invoice_id and confirmed_by
     * (a provider user) are plain indexed columns (no FK). gateway +
     * external_id is the second guard against settling the same payment
     * twice (rows without an external_id never collide).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->index();
            $table->tenantId();
            $table->unsignedBigInteger('amount');
            $table->string('gateway', 40);
            $table->string('method', 40)->nullable();
            $table->string('status', 20)->index();
            $table->string('reference')->nullable();
            $table->string('external_id')->nullable();
            $table->date('paid_on')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable()->index();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
