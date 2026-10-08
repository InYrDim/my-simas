<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A plan now carries named limits (students, staff accounts, storage)
     * in one json column instead of the single max_users, and a visibility
     * flag: a non-public plan is not offered at sign-up but a provider can
     * still assign it to one school. The old max_users becomes the
     * staff_accounts limit.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('limits')->nullable();
            $table->boolean('is_public')->default(true);
        });

        DB::table('plans')->whereNotNull('max_users')->orderBy('id')->each(function (object $plan): void {
            DB::table('plans')->where('id', $plan->id)->update([
                'limits' => json_encode(['staff_accounts' => (int) $plan->max_users]),
            ]);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_users');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_users')->nullable();
        });

        DB::table('plans')->whereNotNull('limits')->orderBy('id')->each(function (object $plan): void {
            $limits = json_decode((string) $plan->limits, true);

            DB::table('plans')->where('id', $plan->id)->update([
                'max_users' => is_array($limits) ? ($limits['staff_accounts'] ?? null) : null,
            ]);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['limits', 'is_public']);
        });
    }
};
