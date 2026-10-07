<?php

namespace Modules\Core\Tests\Unit;

use Modules\Core\App\Infrastructure\Whatsapp\NoticeTemplate;

it('picks one choice per variation group and still fills the variables', function () {
    $seen = [];

    foreach (range(1, 60) as $ignored) {
        $text = NoticeTemplate::fill('{Halo|Selamat pagi|Yth.} {nama_wali}, {nama_siswa} {hadir|tiba}.', [
            'nama_wali' => 'Bu Sari',
            'nama_siswa' => 'Budi',
        ]);

        expect($text)->toMatch('/^(Halo|Selamat pagi|Yth\.) Bu Sari, Budi (hadir|tiba)\.$/');

        $seen[$text] = true;
    }

    expect(count($seen))->toBeGreaterThan(1);
});

it('does not read a value that holds a bar or a brace as a group', function () {
    $text = NoticeTemplate::fill('Halo {nama_siswa}!', ['nama_siswa' => '{a|b} | {x']);

    expect($text)->toBe('Halo {a|b} | {x!');
});

it('leaves a variable without a value as written', function () {
    expect(NoticeTemplate::fill('{nama_siswa} {tanggal}', ['nama_siswa' => 'Budi']))->toBe('Budi {tanggal}');
});

it('previews with the first choice of every group, every time', function () {
    $template = '{Halo|Hai} {nama_siswa}, {sehat|sakit}';

    expect(NoticeTemplate::preview($template, ['nama_siswa' => 'Budi']))->toBe('Halo Budi, sehat')
        ->and(NoticeTemplate::preview($template, ['nama_siswa' => 'Budi']))->toBe('Halo Budi, sehat');
});

it('accepts well formed groups and plain wording', function (string $template) {
    expect(NoticeTemplate::groupError($template))->toBeNull();
})->with([
    'plain' => ['Halo {nama_siswa}'],
    'one group' => ['{Halo|Hai} {nama_siswa}'],
    'two groups' => ['{a|b|c} dan {d|e}'],
    'a bar outside any group' => ['Halo | {nama_siswa}'],
]);

it('refuses a group that is empty, has an empty choice, or is nested', function (string $template) {
    expect(NoticeTemplate::groupError($template))->not->toBeNull();
})->with([
    'empty choice at the end' => ['{Halo|} {nama_siswa}'],
    'empty choice in the middle' => ['{a||b} {nama_siswa}'],
    'only bars' => ['{|}'],
    'blank choice' => ['{a| } {nama_siswa}'],
    'nested' => ['{a|{b|c}} {nama_siswa}'],
    'not closed' => ['{a|b {nama_siswa}'],
]);

it('does not call a lone variable in braces a group', function () {
    expect(NoticeTemplate::groupError('{nama_siswa}'))->toBeNull();
});
