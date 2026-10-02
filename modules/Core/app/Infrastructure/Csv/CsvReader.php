<?php

namespace Modules\Core\App\Infrastructure\Csv;

use Modules\Core\App\Domain\Exceptions\CsvFormatException;

/**
 * Reads a CSV file into rows keyed by column name. Tolerant of what
 * spreadsheet programs produce: a UTF-8 byte order mark, a `sep=` first
 * line, `;` or `,` as the separator, Windows-1252 text and blank lines.
 */
final class CsvReader
{
    public const MAX_ROWS = 1000;

    private const BOM = "\xEF\xBB\xBF";

    /**
     * @param  list<string>  $requiredColumns
     * @return list<array{line: int, values: array<string, string>}>
     *
     * @throws CsvFormatException
     */
    public function read(string $path, array $requiredColumns = []): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new CsvFormatException('Berkas tidak bisa dibaca.');
        }

        try {
            return $this->readRows($handle, $requiredColumns);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $requiredColumns
     * @return list<array{line: int, values: array<string, string>}>
     */
    private function readRows($handle, array $requiredColumns): array
    {
        $line = 1;
        $first = fgets($handle);

        if ($first === false) {
            throw new CsvFormatException('Berkas kosong.');
        }

        $first = str_starts_with($first, self::BOM) ? substr($first, strlen(self::BOM)) : $first;

        if (preg_match('/^sep=(.)\s*$/i', $first, $matches) === 1) {
            $delimiter = $matches[1];
            $first = (string) fgets($handle);
            $line++;
        } else {
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        }

        $header = array_map($this->columnName(...), str_getcsv($first, $delimiter, '"', ''));

        if (array_filter($header) === []) {
            throw new CsvFormatException('Berkas kosong.');
        }

        $missing = array_values(array_diff($requiredColumns, $header));

        if ($missing !== []) {
            throw new CsvFormatException('Kolom wajib tidak ditemukan: '.implode(', ', $missing).'.');
        }

        $rows = [];

        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $line++;
            $cells = array_map(fn (?string $cell): string => $this->text((string) $cell), $cells);

            if (implode('', $cells) === '') {
                continue;
            }

            if (count($rows) === self::MAX_ROWS) {
                throw new CsvFormatException('Berkas melebihi '.number_format(self::MAX_ROWS, 0, ',', '.').' baris data. Bagi menjadi beberapa berkas.');
            }

            $values = [];

            foreach ($header as $index => $column) {
                if ($column !== '') {
                    $values[$column] = $cells[$index] ?? '';
                }
            }

            $rows[] = ['line' => $line, 'values' => $values];
        }

        if ($rows === []) {
            throw new CsvFormatException('Berkas tidak berisi baris data.');
        }

        return $rows;
    }

    /**
     * "Jenis Kelamin" and "jenis-kelamin" both name the column `jenis_kelamin`.
     */
    private function columnName(?string $header): string
    {
        $name = mb_strtolower($this->text((string) $header));

        return trim((string) preg_replace('/[\s\-]+/', '_', $name), '_');
    }

    private function text(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return trim($value);
    }
}
