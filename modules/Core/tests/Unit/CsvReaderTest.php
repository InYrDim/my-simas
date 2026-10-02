<?php

namespace Modules\Core\Tests\Unit;

use Modules\Core\App\Domain\Exceptions\CsvFormatException;
use Modules\Core\App\Infrastructure\Csv\CsvReader;

/**
 * Writes the content to a temporary file and reads it back as CSV.
 *
 * @param  list<string>  $requiredColumns
 * @return list<array{line: int, values: array<string, string>}>
 */
function readCsv(string $content, array $requiredColumns = []): array
{
    $path = (string) tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $content);

    try {
        return (new CsvReader)->read($path, $requiredColumns);
    } finally {
        unlink($path);
    }
}

it('reads rows keyed by column name, with their line number', function () {
    $rows = readCsv("nama,nis\nBudi,001\nSari,002\n");

    expect($rows)->toBe([
        ['line' => 2, 'values' => ['nama' => 'Budi', 'nis' => '001']],
        ['line' => 3, 'values' => ['nama' => 'Sari', 'nis' => '002']],
    ]);
});

it('reads a semicolon separated file', function () {
    $rows = readCsv("nama;nis\nBudi;001\n");

    expect($rows[0]['values'])->toBe(['nama' => 'Budi', 'nis' => '001']);
});

it('ignores a byte order mark', function () {
    $rows = readCsv("\xEF\xBB\xBFnama,nis\nBudi,001\n");

    expect($rows[0]['values'])->toBe(['nama' => 'Budi', 'nis' => '001']);
});

it('follows a sep= first line', function () {
    $rows = readCsv("sep=;\r\nnama;nis\r\nBudi, S.Pd;001\r\n");

    expect($rows)->toBe([
        ['line' => 3, 'values' => ['nama' => 'Budi, S.Pd', 'nis' => '001']],
    ]);
});

it('normalises column names', function () {
    $rows = readCsv(" Nama , Jenis Kelamin ,tanggal-lahir\nBudi,L,2010-05-17\n");

    expect(array_keys($rows[0]['values']))->toBe(['nama', 'jenis_kelamin', 'tanggal_lahir']);
});

it('trims cells and fills cells a short row leaves out', function () {
    $rows = readCsv("nama,nis,kelas\n  Budi  ,001\n");

    expect($rows[0]['values'])->toBe(['nama' => 'Budi', 'nis' => '001', 'kelas' => '']);
});

it('keeps a quoted cell that contains the separator', function () {
    $rows = readCsv("nama,nis\n\"Santoso, Budi\",001\n");

    expect($rows[0]['values']['nama'])->toBe('Santoso, Budi');
});

it('skips blank lines but keeps counting them', function () {
    $rows = readCsv("nama,nis\n\n,\nBudi,001\n");

    expect($rows)->toBe([
        ['line' => 4, 'values' => ['nama' => 'Budi', 'nis' => '001']],
    ]);
});

it('converts Windows-1252 text to UTF-8', function () {
    $rows = readCsv("nama,nis\nRen\xE9,001\n");

    expect($rows[0]['values']['nama'])->toBe('René');
});

it('refuses a file without a required column', function () {
    readCsv("nama,kelas\nBudi,X 1\n", ['nama', 'nis', 'jenis_kelamin']);
})->throws(CsvFormatException::class, 'Kolom wajib tidak ditemukan: nis, jenis_kelamin.');

it('refuses an empty file', function (string $content) {
    readCsv($content);
})->with([
    'no bytes' => [''],
    'only a blank line' => ["\n"],
])->throws(CsvFormatException::class, 'Berkas kosong.');

it('refuses a file with a header only', function () {
    readCsv("nama,nis\n");
})->throws(CsvFormatException::class, 'Berkas tidak berisi baris data.');

it('reads up to the row limit and refuses one row more', function () {
    $rows = fn (int $count): string => "nama,nis\n".implode('', array_map(fn (int $i): string => "Siswa {$i},{$i}\n", range(1, $count)));

    expect(readCsv($rows(CsvReader::MAX_ROWS)))->toHaveCount(CsvReader::MAX_ROWS);

    expect(fn () => readCsv($rows(CsvReader::MAX_ROWS + 1)))->toThrow(CsvFormatException::class, 'Berkas melebihi 1.000 baris data.');
});
