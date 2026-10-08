<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Billing contact (where invoices go), the exemption flag (a school the
     * provider never bills, suspends or reminds), and why a school is
     * suspended. A school suspended before this column existed was
     * suspended by the provider by hand, so it is marked 'manual': a
     * payment must never reopen it.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('billing_email')->nullable();
            $table->string('billing_name')->nullable();
            $table->boolean('billing_exempt')->default(false);
            $table->string('suspended_reason', 20)->nullable();
        });

        DB::table('tenants')->where('status', 'suspended')->update(['suspended_reason' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['billing_email', 'billing_name', 'billing_exempt', 'suspended_reason']);
        });
    }
};
