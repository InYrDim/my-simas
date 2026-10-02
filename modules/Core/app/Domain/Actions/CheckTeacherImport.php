<?php

namespace Modules\Core\App\Domain\Actions;

use Modules\Core\App\Domain\Enums\ImportMode;
use Modules\Core\App\Domain\Models\Teacher;

/**
 * Checks the rows of a teacher CSV against the field rules of the teacher
 * form and the school's data, without writing anything. A row comes out
 * as `create`, `update` (upsert mode, NIP already registered) or `error`.
 *
 * A teacher is recognised by NIP only: a row without one is always new.
 * In an update an empty cell keeps the stored value. `data` is what
 * SaveTeacher receives, null for a row with errors.
 *
 * @phpstan-type TeacherData array{name: string, nip?: string|null, nuptk?: string|null, employment: string, duty: string, email?: string|null}
 * @phpstan-type CheckedRow array{line: int, key: string, name: string, detail: string, outcome: 'create'|'update'|'error', messages: list<string>, id: int|null, data: TeacherData|null}
 */
final class CheckTeacherImport
{
    private const EMPLOYMENTS = ['PNS', 'GTY', 'GTT', 'Honorer'];

    private const DUTIES = ['Guru Mapel', 'Tenaga Kependidikan', 'Kepala Sekolah'];

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return list<CheckedRow>
     */
    public function handle(array $rows, ImportMode $mode): array
    {
        $idsByNip = Teacher::query()->whereNotNull('nip')->get(['id', 'nip'])
            ->mapWithKeys(fn (Teacher $teacher): array => [mb_strtolower((string) $teacher->nip) => $teacher->id])
            ->all();

        $linesByNip = [];
        $checked = [];

        foreach ($rows as $row) {
            $cell = fn (string $column): string => $row['values'][$column] ?? '';

            $name = $cell('nama');
            $nip = $cell('nip');
            $email = $cell('email');
            $nipKey = mb_strtolower($nip);
            $existingId = $idsByNip[$nipKey] ?? null;
            $updates = $mode === ImportMode::Upsert && $existingId !== null;

            $messages = [
                ...$this->checkText('Nama', $name, 255, required: true),
                ...$this->checkNumber('NIP', $nip),
                ...$this->checkNumber('NUPTK', $cell('nuptk')),
            ];

            $employment = $this->choice($cell('status_kepegawaian'), self::EMPLOYMENTS);

            if ($employment === null) {
                $messages[] = $cell('status_kepegawaian') === ''
                    ? 'Status kepegawaian wajib diisi.'
                    : 'Status kepegawaian harus salah satu dari: '.implode(', ', self::EMPLOYMENTS).'.';
            }

            $duty = $this->choice($cell('tugas'), self::DUTIES);

            if ($duty === null) {
                $messages[] = $cell('tugas') === ''
                    ? 'Tugas wajib diisi.'
                    : 'Tugas harus salah satu dari: '.implode(', ', self::DUTIES).'.';
            }

            if ($email !== '' && (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255)) {
                $messages[] = 'Email harus berupa alamat email yang valid.';
            }

            if ($nip !== '') {
                if (isset($linesByNip[$nipKey])) {
                    $messages[] = "NIP sudah muncul di baris {$linesByNip[$nipKey]}.";
                } elseif ($existingId !== null && ! $updates) {
                    $messages[] = 'NIP sudah terdaftar.';
                }

                $linesByNip[$nipKey] ??= $row['line'];
            }

            $data = null;

            if ($messages === [] && $employment !== null && $duty !== null) {
                $data = [
                    'name' => $name,
                    'nip' => $nip === '' ? null : $nip,
                    'nuptk' => $cell('nuptk') === '' ? null : $cell('nuptk'),
                    'employment' => $employment,
                    'duty' => $duty,
                    'email' => $email === '' ? null : $email,
                ];

                if ($updates) {
                    $data = array_filter($data, fn (?string $value): bool => $value !== null);
                }
            }

            $checked[] = [
                'line' => $row['line'],
                'key' => $nip,
                'name' => $name,
                'detail' => $duty ?? $cell('tugas'),
                'outcome' => $data === null ? 'error' : ($updates ? 'update' : 'create'),
                'messages' => $messages,
                'id' => $updates ? $existingId : null,
                'data' => $data,
            ];
        }

        return $checked;
    }

    /**
     * The option as it is stored, matched without regard to letter case.
     *
     * @param  list<string>  $options
     */
    private function choice(string $value, array $options): ?string
    {
        foreach ($options as $option) {
            if (mb_strtolower($option) === mb_strtolower($value)) {
                return $option;
            }
        }

        return null;
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
     * that treated an 18-digit NIP as a number turns it into `1,98E+17`,
     * which has lost its last digits.
     *
     * @return list<string>
     */
    private function checkNumber(string $label, string $value): array
    {
        if (preg_match('/^\d+([.,]\d+)?E\+?\d+$/i', $value) === 1) {
            return ["{$label} terbaca sebagai notasi ilmiah ({$value}). Format kolomnya sebagai Teks di Excel, lalu isi ulang."];
        }

        return $this->checkText($label, $value, 32);
    }
}
