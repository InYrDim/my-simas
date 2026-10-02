<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The registration form of a period becomes rows: `ppdb_form_fields`
     * holds every field in order (the ten built-in ones of the applicant
     * record, and the custom ones a school adds) and `ppdb_applicant_answers`
     * the answers to the custom ones. The choice the earlier
     * `ppdb_periods.form_fields` column held (required / optional / off per
     * built-in field) is carried over into rows — off becomes archived — and
     * the column is dropped. Plain ids, no foreign keys but tenant_id.
     */
    public function up(): void
    {
        Schema::create('ppdb_form_fields', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('period_id');
            $table->string('key', 32)->nullable();
            $table->string('type', 16);
            $table->string('label', 150);
            $table->string('help', 300)->nullable();
            $table->boolean('required')->default(false);
            $table->json('options')->nullable();
            $table->json('rules')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'period_id', 'key']);
            $table->index(['tenant_id', 'period_id', 'sort_order']);
        });

        Schema::create('ppdb_applicant_answers', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->unsignedBigInteger('applicant_id');
            $table->unsignedBigInteger('field_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'applicant_id', 'field_id']);
            $table->index(['tenant_id', 'field_id']);
        });

        $this->carryOver();

        Schema::table('ppdb_periods', function (Blueprint $table) {
            $table->dropColumn('form_fields');
        });
    }

    public function down(): void
    {
        Schema::table('ppdb_periods', function (Blueprint $table) {
            $table->json('form_fields')->nullable()->after('status');
        });

        Schema::dropIfExists('ppdb_applicant_answers');
        Schema::dropIfExists('ppdb_form_fields');
    }

    /**
     * The built-in fields of every existing period, in the order the form
     * always had, in the state the period's old choice gave them.
     */
    private function carryOver(): void
    {
        // key => [label, required by default, locked]
        $builtin = [
            'path_id' => ['Jalur', true, true],
            'name' => ['Nama lengkap', true, true],
            'gender' => ['Jenis kelamin', true, true],
            'nisn' => ['NISN', false, false],
            'birth_place' => ['Tempat lahir', false, false],
            'birth_date' => ['Tanggal lahir', true, false],
            'origin_school' => ['Asal sekolah', true, false],
            'address' => ['Alamat', false, false],
            'guardian_name' => ['Nama wali', true, false],
            'guardian_phone' => ['Telepon wali', true, false],
        ];

        foreach (DB::table('ppdb_periods')->get(['id', 'tenant_id', 'form_fields']) as $period) {
            $now = now();
            $order = 0;
            $rows = [];

            foreach ($builtin as $key => [$label, $required, $locked]) {
                $choice = $locked ? null : $this->choice($period->form_fields, $key);

                $rows[] = [
                    'tenant_id' => $period->tenant_id,
                    'period_id' => $period->id,
                    'key' => $key,
                    'type' => 'builtin',
                    'label' => $label,
                    'required' => $choice === null ? $required : $choice === 'required',
                    'sort_order' => $order++,
                    'archived_at' => $choice === 'off' ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('ppdb_form_fields')->insert($rows);
        }
    }

    /**
     * What the old column said about a field: `required`, `optional`, `off`
     * or nothing.
     */
    private function choice(mixed $json, string $key): ?string
    {
        $decoded = json_decode((string) $json, true);
        $value = is_array($decoded) ? ($decoded[$key] ?? null) : null;

        return is_string($value) ? $value : null;
    }
};
