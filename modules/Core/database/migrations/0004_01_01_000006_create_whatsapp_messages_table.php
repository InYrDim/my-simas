<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every WhatsApp message a school tried to send: who it was for, what
     * it said and what became of it. `student_id` is set when the message
     * is about a student (a plain id: students may be deleted, the log
     * stays).
     */
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('student_id')->nullable();
            $table->string('recipient_name');
            $table->string('phone', 20)->nullable();
            $table->string('kind', 64);
            $table->text('body');
            $table->string('status', 16);
            $table->string('gateway_message_id')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
