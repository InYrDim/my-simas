<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A school's student-card picture and where the static QR goes on it:
     * one row per school. The picture itself lives in the school's private
     * storage; `qr_x`, `qr_y` and `qr_size` are percentages of the picture
     * (position from its left and top edge, size as a share of its width).
     */
    public function up(): void
    {
        Schema::create('static_qr_card_templates', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->string('image_path');
            $table->string('image_name');
            $table->string('image_mime', 32);
            $table->unsignedInteger('image_width');
            $table->unsignedInteger('image_height');
            $table->decimal('qr_x', 6, 3);
            $table->decimal('qr_y', 6, 3);
            $table->decimal('qr_size', 6, 3);
            $table->decimal('card_width_mm', 6, 2)->default(85.6);
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('static_qr_card_templates');
    }
};
