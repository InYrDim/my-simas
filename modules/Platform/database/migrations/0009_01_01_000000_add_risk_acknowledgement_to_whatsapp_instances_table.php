<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who in the school accepted the warning about unofficial WhatsApp,
     * and when. `risk_acknowledged_by` is a plain school user id, no
     * foreign key.
     */
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->unsignedBigInteger('risk_acknowledged_by')->nullable()->after('last_error');
            $table->timestamp('risk_acknowledged_at')->nullable()->after('risk_acknowledged_by');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->dropColumn(['risk_acknowledged_by', 'risk_acknowledged_at']);
        });
    }
};
