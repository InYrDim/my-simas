<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

dataset('importPages', [
    'page' => ['/kelola/impor'],
    'student template' => ['/kelola/impor/templat/students'],
    'teacher template' => ['/kelola/impor/templat/teachers'],
]);

it('opens the import page for a school admin', function () {
    $tenant = schoolAs('impor-admin');

    get(school($tenant->slug, '/kelola/impor'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Core/Manage/Import/Index')
        ->where('targets.0.value', 'students')
        ->where('targets.0.required', ['nama', 'nis', 'jenis_kelamin'])
        ->where('targets.1.value', 'teachers')
        ->where('maxRows', 1000)
    );
});

it('refuses roles that cannot manage master data', function (string $role, string $path) {
    $tenant = schoolAs("impor-{$role}", $role);

    get(school($tenant->slug, $path))->assertForbidden();
})->with(['guru', 'staf-tu'])->with('importPages');

it('hides the Impor Data sidebar entry from roles that cannot manage master data', function (string $role) {
    $tenant = schoolAs("impor-nav-{$role}", $role);

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => ! collect($nav)->pluck('label')->contains('Impor Data'))
    );
})->with(['guru', 'staf-tu']);

it('sends guests to the login', function (string $path) {
    $tenant = TenantFactory::new()->create(['slug' => 'impor-guest']);

    get(school($tenant->slug, $path))->assertRedirect();
})->with('importPages');

it('downloads the CSV template of each target', function (string $target, string $fileName, string $header) {
    $tenant = schoolAs('impor-templat');

    $response = get(school($tenant->slug, "/kelola/impor/templat/{$target}"))
        ->assertOk()
        ->assertDownload($fileName);

    $lines = explode("\r\n", trim($response->streamedContent()));

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toBe('sep=;')
        ->and($lines[1])->toBe($header)
        ->and(substr_count($lines[2], ';'))->toBe(substr_count($header, ';'));
})->with([
    'students' => ['students', 'templat-siswa.csv', 'nama;nis;nisn;jenis_kelamin;tanggal_lahir;nama_wali;telepon_wali;kelas'],
    'teachers' => ['teachers', 'templat-guru.csv', 'nama;nip;nuptk;status_kepegawaian;tugas;email'],
]);

it('answers 404 for an unknown template target', function () {
    $tenant = schoolAs('impor-templat-salah');

    get(school($tenant->slug, '/kelola/impor/templat/classes'))->assertNotFound();
});
