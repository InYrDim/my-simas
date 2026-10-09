<?php

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;
use Modules\Attendance\App\Domain\Models\StaticQrCardTemplate;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * The student-card template of the static QR: a picture, the place of the
 * QR on it, and the printed card.
 */
beforeEach(function () {
    Storage::fake('local');
});

function cardSchool(string $slug, bool $staticQr = true): Tenant
{
    $tenant = attendanceTenant(slug: $slug);

    if ($staticQr) {
        put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '07:00', 'static_qr_enabled' => true])
            ->assertSessionHasNoErrors();
    }

    return $tenant;
}

function uploadCard(Tenant $tenant, ?UploadedFile $file = null): TestResponse
{
    return post(school($tenant->slug, '/absensi/qr-statis/kartu/gambar'), [
        'image' => $file ?? UploadedFile::fake()->image('kartu.png', 856, 540),
    ]);
}

function cardTemplate(Tenant $tenant): ?StaticQrCardTemplate
{
    return attendanceSchool($tenant, fn () => StaticQrCardTemplate::current());
}

it('stores the picture in the school storage and centres the QR box', function () {
    $tenant = cardSchool('kartu-unggah');

    uploadCard($tenant)->assertRedirect()->assertSessionHasNoErrors();

    $template = cardTemplate($tenant);

    expect($template)->not->toBeNull()
        ->and($template->image_width)->toBe(856)
        ->and($template->image_height)->toBe(540)
        ->and($template->qr_size)->toBe(30.0)
        ->and($template->qr_x)->toBe(35.0);
    Storage::disk('local')->assertExists("tenants/{$tenant->id}/attendance/{$template->image_path}");

    get(school($tenant->slug, '/absensi/qr-statis/kartu'))->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/StaticQrCard')
        ->where('template.size', 30)
        ->where('template.imageName', 'kartu.png')
    );
});

it('refuses a picture that is not a usable PNG or JPG', function (string $name, UploadedFile $file) {
    $tenant = cardSchool('kartu-tolak-'.$name);

    uploadCard($tenant, $file)->assertSessionHasErrors('image');

    expect(cardTemplate($tenant))->toBeNull();
})->with([
    'a document' => ['doc', fn () => UploadedFile::fake()->create('kartu.pdf', 100, 'application/pdf')],
    'a svg' => ['svg', fn () => UploadedFile::fake()->createWithContent('kartu.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><script>alert(1)</script></svg>')],
    'too small' => ['kecil', fn () => UploadedFile::fake()->image('kartu.png', 100, 60)],
    'too big' => ['besar', fn () => UploadedFile::fake()->image('kartu.png', 900, 600)->size(6000)],
]);

it('replaces the picture, deleting the old file and putting the box back in the middle', function () {
    $tenant = cardSchool('kartu-ganti');
    uploadCard($tenant);
    $old = cardTemplate($tenant);
    put(school($tenant->slug, '/absensi/qr-statis/kartu'), ['qr_x' => 10, 'qr_y' => 10, 'qr_size' => 20, 'card_width_mm' => 90])
        ->assertSessionHasNoErrors();

    uploadCard($tenant, UploadedFile::fake()->image('baru.jpg', 600, 600))->assertSessionHasNoErrors();
    $new = cardTemplate($tenant);

    expect(attendanceSchool($tenant, fn () => StaticQrCardTemplate::query()->count()))->toBe(1)
        ->and($new->image_path)->not->toBe($old->image_path)
        ->and($new->image_mime)->toBe('image/jpeg')
        ->and($new->qr_x)->toBe(35.0)
        ->and($new->qr_y)->toBe(35.0)
        ->and($new->card_width_mm)->toBe(90.0);
    Storage::disk('local')->assertMissing("tenants/{$tenant->id}/attendance/{$old->image_path}");
    Storage::disk('local')->assertExists("tenants/{$tenant->id}/attendance/{$new->image_path}");
});

it('saves a QR box that stays on the picture and refuses one that does not', function () {
    $tenant = cardSchool('kartu-letak');
    uploadCard($tenant);
    $url = school($tenant->slug, '/absensi/qr-statis/kartu');

    put($url, ['qr_x' => 60, 'qr_y' => 40, 'qr_size' => 25, 'card_width_mm' => 85.6])->assertSessionHasNoErrors();
    expect(cardTemplate($tenant)->qr_x)->toBe(60.0);

    // 856x540: a box 25% wide is 39.6% tall, so y = 70 runs off the bottom.
    put($url, ['qr_x' => 60, 'qr_y' => 70, 'qr_size' => 25, 'card_width_mm' => 85.6])->assertSessionHasErrors('qr_x');
    put($url, ['qr_x' => 80, 'qr_y' => 10, 'qr_size' => 25, 'card_width_mm' => 85.6])->assertSessionHasErrors('qr_x');
    put($url, ['qr_x' => 10, 'qr_y' => 10, 'qr_size' => 2, 'card_width_mm' => 85.6])->assertSessionHasErrors('qr_size');
    put($url, ['qr_x' => 10, 'qr_y' => 10, 'qr_size' => 20, 'card_width_mm' => 10])->assertSessionHasErrors('card_width_mm');

    expect(cardTemplate($tenant)->qr_x)->toBe(60.0);
});

it('deletes the template with its file', function () {
    $tenant = cardSchool('kartu-hapus');
    uploadCard($tenant);
    $template = cardTemplate($tenant);

    delete(school($tenant->slug, '/absensi/qr-statis/kartu'))->assertRedirect();

    expect(cardTemplate($tenant))->toBeNull();
    Storage::disk('local')->assertMissing("tenants/{$tenant->id}/attendance/{$template->image_path}");
});

it('serves the picture inline and safe to the school only', function () {
    $other = cardSchool('kartu-lain');
    uploadCard($other);

    $tenant = cardSchool('kartu-gambar');
    get(school($tenant->slug, '/absensi/qr-statis/kartu/gambar'))->assertNotFound();

    uploadCard($tenant);
    get(school($tenant->slug, '/absensi/qr-statis/kartu/gambar'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('closes the template while the static QR is off and to anyone but the admin', function () {
    $tenant = cardSchool('kartu-mati', staticQr: false);
    get(school($tenant->slug, '/absensi/qr-statis/kartu'))->assertForbidden();
    uploadCard($tenant)->assertForbidden();

    $teacher = attendanceTenant(role: 'guru', slug: 'kartu-guru');
    attendanceSchool($teacher, fn () => AttendanceSetting::current()->forceFill(['static_qr_enabled' => true])->save());
    get(school($teacher->slug, '/absensi/qr-statis/kartu'))->assertForbidden();
    uploadCard($teacher)->assertForbidden();
    expect(cardTemplate($teacher))->toBeNull();
});

it('gives the print page the template, or none for the plain sheet', function () {
    $tenant = cardSchool('kartu-cetak');
    $adit = attendanceStudent($tenant, attendanceClass($tenant, 'X 1'), 'Adit');

    get(school($tenant->slug, '/absensi/qr-statis'))->assertInertia(fn (Assert $page) => $page
        ->where('template', null)
        ->where('embed', false)
    );

    uploadCard($tenant);

    get(school($tenant->slug, '/absensi/qr-statis?siswa='.$adit->id.'&embed=1'))->assertInertia(fn (Assert $page) => $page
        ->where('embed', true)
        ->where('template.size', 30)
        ->where('template.widthMm', 85.6)
    );

    // Only the dialog's own layout may be framed, and only by this site.
    get(school($tenant->slug, '/absensi/qr-statis?siswa='.$adit->id.'&embed=1'))->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    get(school($tenant->slug, '/absensi/qr-statis'))->assertHeader('X-Frame-Options', 'DENY');

    get(school($tenant->slug, '/absensi/qr-statis?format=polos'))->assertInertia(fn (Assert $page) => $page
        ->where('template', null)
    );
});
