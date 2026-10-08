<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * scheduled_plan_id: a downgrade waiting for the end of the paid
     * period (plain indexed column, no FK). invoices.kind: what an invoice
     * is for; existing rows count as renewals. tenant_modules.source: who
     * set a flag, so a plan change only touches flags the plan set;
     * existing flags count as the plan's, which keeps today's behaviour.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('scheduled_plan_id')->nullable()->index();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('kind', 20)->default('renewal');
        });

        Schema::table('tenant_modules', function (Blueprint $table) {
            $table->string('source', 20)->default('plan');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_modules', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['scheduled_plan_id']);
            $table->dropColumn('scheduled_plan_id');
        });
    }
};
