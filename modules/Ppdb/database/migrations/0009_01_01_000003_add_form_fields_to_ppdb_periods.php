<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which of the form's fields a period asks for: `form_fields` holds the
     * school's choice per field (required, optional, off); empty means the
     * usual form. A field a period switches off is not asked, so the
     * applicant columns that used to be required may now be empty.
     */
    public function up(): void
    {
        Schema::table('ppdb_periods', function (Blueprint $table) {
            $table->json('form_fields')->nullable()->after('status');
        });

        Schema::table('ppdb_applicants', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->change();
            $table->string('origin_school')->nullable()->change();
            $table->string('guardian_name')->nullable()->change();
            $table->string('guardian_phone', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ppdb_applicants', function (Blueprint $table) {
            $table->date('birth_date')->nullable(false)->change();
            $table->string('origin_school')->nullable(false)->change();
            $table->string('guardian_name')->nullable(false)->change();
            $table->string('guardian_phone', 32)->nullable(false)->change();
        });

        Schema::table('ppdb_periods', function (Blueprint $table) {
            $table->dropColumn('form_fields');
        });
    }
};
