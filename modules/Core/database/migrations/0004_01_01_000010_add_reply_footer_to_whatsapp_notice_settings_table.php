<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A school may end a kind of notice with a line that asks the guardian
     * to reply. `reply_footer_text` null = the default line.
     */
    public function up(): void
    {
        Schema::table('whatsapp_notice_settings', function (Blueprint $table) {
            $table->boolean('reply_footer')->default(false)->after('template');
            $table->text('reply_footer_text')->nullable()->after('reply_footer');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_notice_settings', function (Blueprint $table) {
            $table->dropColumn(['reply_footer', 'reply_footer_text']);
        });
    }
};
