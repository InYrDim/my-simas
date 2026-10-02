<?php

namespace Modules\Ppdb\App\Domain\Support;

use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\FieldType;

/**
 * The basic validation a school may put on a custom field, per type: what
 * is kept of what the builder sends, with defaults, and whether it makes
 * sense. Only the keys a type knows are kept.
 *
 * - text: `max_length` (1–500, optional), `format` (free, digits, email, phone)
 * - paragraph: `max_length` (1–2000, optional)
 * - number: `min`, `max` (optional)
 * - date: `allow_future` (default yes)
 * - file: `max_size_kb` (100–5120, default 2048), `kinds` (pdf, image; default both)
 */
final class CustomFieldRules
{
    public const FORMATS = ['free', 'digits', 'email', 'phone'];

    public const KINDS = ['pdf', 'image'];

    public const DEFAULT_FILE_KB = 2048;

    public const MAX_FILE_KB = 5120;

    public const MIN_FILE_KB = 100;

    /**
     * @param  array<string, mixed>  $input  what the builder sent
     * @param  string  $prefix  the error key of the field, e.g. `fields.3`
     * @return array<string, mixed>
     *
     * @throws ValidationException when a value is out of range or contradicts another
     */
    public function normalise(FieldType $type, array $input, string $prefix): array
    {
        return match ($type) {
            FieldType::Text => [
                'max_length' => $this->length($input['max_length'] ?? null, 500, "{$prefix}.rules.max_length"),
                'format' => $this->format($input['format'] ?? null, "{$prefix}.rules.format"),
            ],
            FieldType::Paragraph => [
                'max_length' => $this->length($input['max_length'] ?? null, 2000, "{$prefix}.rules.max_length"),
            ],
            FieldType::Number => $this->numberRange($input, $prefix),
            FieldType::Date => [
                'allow_future' => ! array_key_exists('allow_future', $input) || filter_var($input['allow_future'], FILTER_VALIDATE_BOOLEAN),
            ],
            FieldType::File => $this->fileLimits($input, $prefix),
            default => [],
        };
    }

    /**
     * @throws ValidationException
     */
    private function length(mixed $value, int $ceiling, string $key): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (int) $value < 1 || (int) $value > $ceiling) {
            throw ValidationException::withMessages([$key => "Panjang maksimal harus antara 1 dan {$ceiling}."]);
        }

        return (int) $value;
    }

    /**
     * @throws ValidationException
     */
    private function format(mixed $value, string $key): string
    {
        if ($value === null || $value === '') {
            return 'free';
        }

        if (! is_string($value) || ! in_array($value, self::FORMATS, true)) {
            throw ValidationException::withMessages([$key => 'Format isian tidak valid.']);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{min: int|float|null, max: int|float|null}
     *
     * @throws ValidationException
     */
    private function numberRange(array $input, string $prefix): array
    {
        $range = [];

        foreach (['min', 'max'] as $bound) {
            $value = $input[$bound] ?? null;

            if ($value === null || $value === '') {
                $range[$bound] = null;

                continue;
            }

            if (! is_numeric($value)) {
                throw ValidationException::withMessages(["{$prefix}.rules.{$bound}" => 'Batas harus berupa angka.']);
            }

            $range[$bound] = $value + 0;
        }

        if ($range['min'] !== null && $range['max'] !== null && $range['min'] > $range['max']) {
            throw ValidationException::withMessages(["{$prefix}.rules.max" => 'Nilai maksimal tidak boleh di bawah nilai minimal.']);
        }

        return ['min' => $range['min'], 'max' => $range['max']];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{max_size_kb: int, kinds: list<string>}
     *
     * @throws ValidationException
     */
    private function fileLimits(array $input, string $prefix): array
    {
        $size = $input['max_size_kb'] ?? null;

        if ($size === null || $size === '') {
            $size = self::DEFAULT_FILE_KB;
        }

        if (! is_numeric($size) || (int) $size < self::MIN_FILE_KB || (int) $size > self::MAX_FILE_KB) {
            throw ValidationException::withMessages(["{$prefix}.rules.max_size_kb" => 'Ukuran maksimal harus antara '.self::MIN_FILE_KB.' dan '.self::MAX_FILE_KB.' KB.']);
        }

        $kinds = $input['kinds'] ?? self::KINDS;
        $kinds = is_array($kinds) ? array_values(array_unique(array_map('strval', $kinds))) : [];

        if ($kinds === [] || array_diff($kinds, self::KINDS) !== []) {
            throw ValidationException::withMessages(["{$prefix}.rules.kinds" => 'Pilih jenis berkas yang diterima (PDF atau gambar).']);
        }

        return ['max_size_kb' => (int) $size, 'kinds' => $kinds];
    }
}
