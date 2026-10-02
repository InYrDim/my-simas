<?php

namespace Modules\Core\App\Infrastructure\Csv;

use RuntimeException;

/**
 * Writes a table as the CSV a spreadsheet program opens cleanly: a `sep=;`
 * line so the columns split whatever the computer's regional settings
 * are, `;` between cells and CRLF line ends — the same shape CsvReader
 * takes back in.
 *
 * A text cell that a spreadsheet would run as a formula (it starts with
 * `=`, `+`, `-` or `@`) gets a leading apostrophe, so a name typed into
 * the school's data can never execute on the computer that opens the file.
 */
final class CsvWriter
{
    private const SEPARATOR = ';';

    /**
     * Writes the table to the response body of a streamed download.
     *
     * @param  list<string>  $columns
     * @param  iterable<list<string|int|float|null>>  $rows
     */
    public function output(array $columns, iterable $rows): void
    {
        $stream = fopen('php://output', 'w');

        if ($stream === false) {
            throw new RuntimeException('The output stream could not be opened.');
        }

        $this->write($stream, $columns, $rows);
        fclose($stream);
    }

    /**
     * @param  resource  $stream
     * @param  list<string>  $columns
     * @param  iterable<list<string|int|float|null>>  $rows
     */
    public function write($stream, array $columns, iterable $rows): void
    {
        fwrite($stream, 'sep='.self::SEPARATOR."\r\n");

        $this->line($stream, $columns);

        foreach ($rows as $row) {
            $this->line($stream, $row);
        }
    }

    /**
     * @param  resource  $stream
     * @param  list<string|int|float|null>  $cells
     */
    private function line($stream, array $cells): void
    {
        fputcsv($stream, array_map($this->safe(...), $cells), self::SEPARATOR, '"', '', "\r\n");
    }

    private function safe(string|int|float|null $cell): string|int|float|null
    {
        if (is_string($cell) && preg_match('/^[=+\-@\t\r]/', $cell) === 1) {
            return "'".$cell;
        }

        return $cell;
    }
}
