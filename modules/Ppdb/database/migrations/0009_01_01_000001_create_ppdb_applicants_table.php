<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The applicants of a school's admissions period. `account_id` is the
     * applicant's own PPDB account (central, filled when the applicant
     * registered online; empty for someone the committee entered by hand)
     * and `student_id` the student made at re-registration — both plain
     * ids, no foreign keys but tenant_id. Days are `Y-m-d` days of the
     * school.
     */
    public function up(): void
    {
        Schema::create('ppdb_applicants', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('wave_id');
            $table->unsignedBigInteger('path_id');
            $table->unsignedBigInteger('account_id')->nullable();

            $table->string('number', 24);
            $table->string('name');
            $table->string('gender', 1);
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date');
            $table->string('nisn', 32)->nullable();
            $table->string('origin_school');
            $table->text('address')->nullable();
            $table->string('guardian_name');
            $table->string('guardian_phone', 32);

            $table->string('source', 16);
            $table->date('registered_on');
            $table->string('status', 16)->default('submitted');
            $table->string('verification_note', 500)->nullable();

            $table->decimal('score', 5, 2)->nullable();
            $table->string('decision', 16)->default('pending');
            $table->timestamp('enrolled_at')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'account_id']);
            $table->index(['tenant_id', 'period_id', 'path_id']);
            $table->index(['tenant_id', 'period_id', 'status']);
            $table->index(['tenant_id', 'wave_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppdb_applicants');
    }
};
