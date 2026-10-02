<?php

namespace Modules\Core\Tests\Unit;

use Modules\Core\App\Infrastructure\Csv\CsvWriter;

/**
 * @param  list<string>  $columns
 * @param  list<list<string|int|float|null>>  $rows
 */
function writeCsv(array $columns, array $rows): string
{
    $stream = fopen('php://memory', 'w+');

    (new CsvWriter)->write($stream, $columns, $rows);
    rewind($stream);

    return (string) stream_get_contents($stream);
}

it('writes the separator line, the header and the rows with CRLF line ends', function () {
    expect(writeCsv(['nama', 'nis'], [['Budi', '0071'], ['Sari', 72]]))
        ->toBe("sep=;\r\nnama;nis\r\nBudi;0071\r\nSari;72\r\n");
});

it('quotes a cell that holds the separator, a quote or a line break', function () {
    expect(writeCsv(['catatan'], [['a;b'], ['kata "kutip"'], ["dua\nbaris"]]))
        ->toBe("sep=;\r\ncatatan\r\n\"a;b\"\r\n\"kata \"\"kutip\"\"\"\r\n\"dua\nbaris\"\r\n");
});

it('writes an empty cell for null', function () {
    expect(writeCsv(['a', 'b', 'c'], [['x', null, 'z']]))->toBe("sep=;\r\na;b;c\r\nx;;z\r\n");
});

it('defuses a text cell a spreadsheet would run as a formula', function (string $cell) {
    expect(writeCsv(['nama'], [[$cell]]))->toContain("\r\n'{$cell}\r\n");
})->with(['=1+1', '+62812', '-5', '@SUM(A1)']);

it('leaves numbers alone, negative ones included', function () {
    expect(writeCsv(['jam'], [[-5], [2.5]]))->toBe("sep=;\r\njam\r\n-5\r\n2.5\r\n");
});
