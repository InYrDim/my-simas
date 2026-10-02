<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The bell schedule template of a school: one row per slot of a
     * weekday (1 = Senin ... 6 = Sabtu). Not tied to an academic year.
     */
    public function up(): void
    {
        Schema::create('period_slots', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedTinyInteger('day');
            $table->time('start_time');
            $table->unique(['tenant_id', 'day', 'start_time']);
            $table->time('end_time');
            $table->string('type', 16);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_slots');
    }
};
