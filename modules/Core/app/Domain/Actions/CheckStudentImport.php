<?php

namespace Modules\Core\App\Domain\Actions;

use DateTimeImmutable;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Enums\ImportMode;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;

/**
 * Checks the rows of a student CSV against the field rules of the student
 * form and the school's data, without writing anything. A row comes out
 * as `create`, `update` (upsert mode, NIS already registered) or `error`.
 *
 * In an update an empty cell keeps the stored value, so only the cells
 * that carry something end up in `data`.
 *
 * @phpstan-type CheckedRow array{line: int, key: string, name: string, detail: string, outcome: 'create'|'update'|'error', messages: list<string>, id: int|null, data: array<string, mixed>}
 */
final class CheckStudentImport
{
    private const GENDERS = [
        'l' => 'L', 'lk' => 'L', 'laki-laki' => 'L', 'laki laki' => 'L',
        'p' => 'P', 'pr' => 'P', 'perempuan' => 'P',
    ];

    private const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'd-m-Y'];

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return list<CheckedRow>
     */
    public function handle(array $rows, ImportMode $mode): array
    {
        $students = Student::query()->get(['id', 'nis', 'nisn']);
        $idsByNis = $students->mapWithKeys(fn (Student $student): array => [mb_strtolower($student->nis) => $student->id])->all();
        $idsByNisn = $students->whereNotNull('nisn')->mapWithKeys(fn (Student $student): array => [mb_strtolower((string) $student->nisn) => $student->id])->all();
        $classIdsByName = $this->activeYearClasses();

        $linesByNis = [];
        $linesByNisn = [];
        $checked = [];

        foreach ($rows as $row) {
            $cell = fn (string $column): string => $row['values'][$column] ?? '';

            $name = $cell('nama');
            $nis = $cell('nis');
            $nisn = $cell('nisn');
            $nisKey = mb_strtolower($nis);
            $nisnKey = mb_strtolower($nisn);
            $className = $cell('kelas');
            $existingId = $idsByNis[$nisKey] ?? null;
            $updates = $mode === ImportMode::Upsert && $existingId !== null;

            $messages = [
                ...$this->checkText('Nama', $name, 255, required: true),
                ...$this->checkNumber('NIS', $nis, required: true),
                ...$this->checkNumber('NISN', $nisn),
                ...$this->checkText('Nama wali', $cell('nama_wali'), 255),
                ...$this->checkText('Telepon wali', $cell('telepon_wali'), 32),
            ];

            $gender = self::GENDERS[mb_strtolower($cell('jenis_kelamin'))] ?? null;

            if ($gender === null) {
                $messages[] = $cell('jenis_kelamin') === '' ? 'Jenis kelamin wajib diisi.' : 'Jenis kelamin harus L atau P.';
            }

            $birthDate = $this->date($cell('tanggal_lahir'));

            if ($birthDate === false) {
                $messages[] = 'Tanggal lahir tidak valid (pakai TTTT-BB-HH atau HH/BB/TTTT).';
            } elseif ($birthDate !== null && $birthDate > today()->format('Y-m-d')) {
                $messages[] = 'Tanggal lahir tidak boleh melewati hari ini.';
            }

            $classId = $className === '' ? null : ($classIdsByName[mb_strtolower($className)] ?? null);

            if ($className !== '' && $classId === null) {
                $messages[] = "Kelas \"{$className}\" tidak ada di tahun ajaran aktif.";
            }

            if ($nis !== '') {
                if (isset($linesByNis[$nisKey])) {
                    $messages[] = "NIS sudah muncul di baris {$linesByNis[$nisKey]}.";
                } elseif ($existingId !== null && ! $updates) {
                    $messages[] = 'NIS sudah terdaftar.';
                }

                $linesByNis[$nisKey] ??= $row['line'];
            }

            if ($nisn !== '') {
                if (isset($linesByNisn[$nisnKey])) {
                    $messages[] = "NISN sudah muncul di baris {$linesByNisn[$nisnKey]}.";
                } elseif (isset($idsByNisn[$nisnKey]) && $idsByNisn[$nisnKey] !== $existingId) {
                    $messages[] = 'NISN sudah dipakai siswa lain.';
                }

                $linesByNisn[$nisnKey] ??= $row['line'];
            }

            $data = [
                'name' => $name,
                'nis' => $nis,
                'nisn' => $nisn === '' ? null : $nisn,
                'gender' => $gender,
                'birth_date' => $birthDate === false ? null : $birthDate,
                'guardian_name' => $cell('nama_wali') === '' ? null : $cell('nama_wali'),
                'guardian_phone' => $cell('telepon_wali') === '' ? null : $cell('telepon_wali'),
                'class_id' => $classId,
            ];

            $checked[] = [
                'line' => $row['line'],
                'key' => $nis,
                'name' => $name,
                'detail' => $className,
                'outcome' => $messages !== [] ? 'error' : ($updates ? 'update' : 'create'),
                'messages' => $messages,
                'id' => $updates ? $existingId : null,
                'data' => $updates ? array_filter($data, fn (mixed $value): bool => $value !== null) : $data,
            ];
        }

        return $checked;
    }

    /**
     * The classes a student can be placed in, by lower-cased name: those
     * of the active academic year (a class name is unique within a year).
     *
     * @return array<string, int>
     */
    private function activeYearClasses(): array
    {
        return ClassGroup::query()
            ->whereHas('academicYear', fn ($query) => $query->where('status', AcademicYearStatus::Active->value))
            ->get(['id', 'name'])
            ->mapWithKeys(fn (ClassGroup $class): array => [mb_strtolower($class->name) => $class->id])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function checkText(string $label, string $value, int $max, bool $required = false): array
    {
        if ($value === '') {
            return $required ? ["{$label} wajib diisi."] : [];
        }

        return mb_strlen($value) > $max ? ["{$label} terlalu panjang (maksimal {$max})."] : [];
    }

    /**
     * An identity number: text of at most 32 characters. A spreadsheet
     * that treated it as a number may have turned it into `1,23E+17`,
     * which has lost its last digits.
     *
     * @return list<string>
     */
    private function checkNumber(string $label, string $value, bool $required = false): array
    {
        if (preg_match('/^\d+([.,]\d+)?E\+?\d+$/i', $value) === 1) {
            return ["{$label} terbaca sebagai notasi ilmiah ({$value}). Format kolomnya sebagai Teks di Excel, lalu isi ulang."];
        }

        return $this->checkText($label, $value, 32, $required);
    }

    /**
     * The date as Y-m-d, null when the cell is empty, false when it is
     * not a date.
     */
    private function date(string $value): string|false|null
    {
        if ($value === '') {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return false;
    }
}
