<?php

namespace Modules\Ppdb\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Modules\Ppdb\App\Domain\Models\FormField;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * File fields: what an applicant may upload, where it is kept, who may
 * download it, and that it goes when it is replaced or the registration is
 * cancelled.
 */

const FILE_FORM = 'http://localhost/calon-siswa/formulir';

beforeEach(function () {
    // 10 February 2027, 10:00 in Jakarta: inside the default wave (1 Jan – 31 Mar).
    $this->travelTo('2027-02-10 03:00:00');
    Storage::fake('local');
});

/**
 * @return list<string> every file on the fake disk
 */
function storedFiles(): array
{
    return Storage::disk('local')->allFiles();
}

it('keeps an uploaded file in the school\'s own private folder under a name of the server\'s making', function () {
    [$tenant, $period, , $path] = ppdbOpenSchool('berkas-simpan');
    $field = customField($tenant, $period, FieldType::File, rules: ['max_size_kb' => 500, 'kinds' => ['pdf']], attributes: ['label' => 'Ijazah']);
    ppdbAccount($tenant);

    post(FILE_FORM, ownApplicationForm($path, ['answers' => [$field->id => UploadedFile::fake()->create('ijazah.pdf', 100, 'application/pdf')]]))
        ->assertSessionHasNoErrors();

    $applicant = ppdbSchool($tenant, fn () => Applicant::query()->sole());
    $answer = json_decode((string) storedAnswer($tenant, $applicant, $field), true);

    expect($answer['name'])->toBe('ijazah.pdf')
        ->and($answer['path'])->toMatch("#^applicants/{$applicant->id}/{$field->id}-[a-z0-9]{20}\\.pdf$#")
        ->and(storedFiles())->toBe(["tenants/{$tenant->id}/ppdb/{$answer['path']}"]);
});

it('keeps only the file name the sender gave, not a path', function () {
    [$tenant, $period, , $path] = ppdbOpenSchool('berkas-nama');
    $field = customField($tenant, $period, FieldType::File);
    ppdbAccount($tenant);

    post(FILE_FORM, ownApplicationForm($path, ['answers' => [$field->id => UploadedFile::fake()->create('../../etc/rahasia.pdf', 50, 'application/pdf')]]))
        ->assertSessionHasNoErrors();

    $applicant = ppdbSchool($tenant, fn () => Applicant::query()->sole());

    expect(json_decode((string) storedAnswer($tenant, $applicant, $field), true)['name'])->toBe('rahasia.pdf');
});

it('refuses a file that is the wrong kind, too big, or missing when required', function (string $name, int $kb, string $mime, string $message) {
    [$tenant, $period, , $path] = ppdbOpenSchool('berkas-tolak');
    $field = customField($tenant, $period, FieldType::File, rules: ['max_size_kb' => 200, 'kinds' => ['pdf']], attributes: ['required' => true, 'label' => 'Ijazah']);
    ppdbAccount($tenant);

    $answers = $name === '' ? [] : [$field->id => UploadedFile::fake()->create($name, $kb, $mime)];

    post(FILE_FORM, ownApplicationForm($path, ['answers' => $answers]))->assertSessionHasErrors("answers.{$field->id}");

    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(0)
        ->and(storedFiles())->toBe([]);
})->with([
    'a text file' => ['catatan.txt', 10, 'text/plain', ''],
    'an image where only pdf is accepted' => ['foto.png', 10, 'image/png', ''],
    'a file over the size limit' => ['besar.pdf', 400, 'application/pdf', ''],
    'no file at all' => ['', 0, '', ''],
]);

it('accepts an image where the field allows images', function () {
    [$tenant, $period, , $path] = ppdbOpenSchool('berkas-gambar');
    $field = customField($tenant, $period, FieldType::File, rules: ['max_size_kb' => 1000, 'kinds' => ['image']]);
    ppdbAccount($tenant);

    post(FILE_FORM, ownApplicationForm($path, ['answers' => [$field->id => UploadedFile::fake()->image('foto.png', 100, 100)]]))
        ->assertSessionHasNoErrors();

    expect(storedFiles())->toHaveCount(1);
});

it('counts a file already sent as given on a correction, and replaces it when a new one comes', function () {
    [$tenant, $period, $wave, $path] = ppdbOpenSchool('berkas-ganti');
    $field = customField($tenant, $period, FieldType::File, attributes: ['required' => true, 'label' => 'Ijazah']);
    $account = ppdbAccount($tenant);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path, ['account_id' => $account->id, 'status' => ApplicantStatus::Revision, 'verification_note' => 'Ganti.']);
    $askAgain = fn () => ppdbSchool($tenant, fn () => Applicant::query()->whereKey($applicant->id)->update(['status' => ApplicantStatus::Revision->value]));

    put(FILE_FORM, ownApplicationForm($path))->assertSessionHasErrors("answers.{$field->id}");

    put(FILE_FORM, ownApplicationForm($path, ['answers' => [$field->id => UploadedFile::fake()->create('lama.pdf', 50, 'application/pdf')]]))->assertSessionHasNoErrors();

    $first = storedFiles();

    expect($first)->toHaveCount(1);

    // The committee asks again: the file already sent meets the required field.
    $askAgain();
    put(FILE_FORM, ownApplicationForm($path))->assertSessionHasNoErrors();

    expect(storedFiles())->toBe($first);

    // A new file replaces it, and the old one is gone from the disk.
    $askAgain();
    put(FILE_FORM, ownApplicationForm($path, ['answers' => [$field->id => UploadedFile::fake()->create('baru.pdf', 50, 'application/pdf')]]))->assertSessionHasNoErrors();

    expect(storedFiles())->toHaveCount(1)
        ->and(storedFiles())->not->toBe($first)
        ->and(json_decode((string) storedAnswer($tenant, $applicant, $field), true)['name'])->toBe('baru.pdf');
});

it('lets the committee replace a file and removes the old one from the disk', function () {
    $tenant = ppdbTenant(slug: 'berkas-panitia');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $field = customField($tenant, $period, FieldType::File, attributes: ['required' => true]);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);
    $url = school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}");

    put($url, [...applicantForm($wave, $path), 'answers' => [$field->id => UploadedFile::fake()->create('satu.pdf', 50, 'application/pdf')]])->assertSessionHasNoErrors();

    $first = storedFiles();

    // Saving the data again without a file keeps the file and still counts as given.
    put($url, [...applicantForm($wave, $path, ['name' => 'Nama Baru'])])->assertSessionHasNoErrors();

    expect(storedFiles())->toBe($first);

    put($url, [...applicantForm($wave, $path), 'answers' => [$field->id => UploadedFile::fake()->create('dua.pdf', 50, 'application/pdf')]])->assertSessionHasNoErrors();

    $now = storedFiles();

    expect($now)->toHaveCount(1)
        ->and($now)->not->toBe($first)
        ->and(json_decode((string) storedAnswer($tenant, $applicant, $field), true)['name'])->toBe('dua.pdf');
});

it('hands the committee the file as a download and nothing else', function () {
    $tenant = ppdbTenant(slug: 'berkas-unduh');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $field = customField($tenant, $period, FieldType::File);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);
    $url = school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}");

    put($url, [...applicantForm($wave, $path), 'answers' => [$field->id => UploadedFile::fake()->create('ijazah.pdf', 50, 'application/pdf')]])->assertSessionHasNoErrors();

    $download = get(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/berkas/{$field->id}"));

    $download->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($download->headers->get('Content-Disposition'))->toContain('attachment')->toContain('ijazah.pdf');

    get($url)->assertInertia(fn (Assert $page) => $page
        ->where("files.{$field->id}.name", 'ijazah.pdf')
        ->where("files.{$field->id}.url", route('ppdb.applicants.file', ['applicant' => $applicant->id, 'field' => $field->id]))
        ->missing("answers.{$field->id}")
    );
});

it('answers 404 for a download that has no file, a text field, or another school', function () {
    $tenant = ppdbTenant(slug: 'berkas-404');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $file = customField($tenant, $period, FieldType::File);
    $text = customField($tenant, $period, FieldType::Text);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);
    ppdbSchool($tenant, fn () => ApplicantAnswer::factory()->create(['applicant_id' => $applicant->id, 'field_id' => $text->id, 'value' => 'teks']));

    get(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/berkas/{$file->id}"))->assertNotFound();
    get(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/berkas/{$text->id}"))->assertNotFound();

    put(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"), [...applicantForm($wave, $path), 'answers' => [$file->id => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertSessionHasNoErrors();

    $other = ppdbTenant(slug: 'berkas-404-lain');

    get(school($other->slug, "/ppdb/pendaftar/{$applicant->id}/berkas/{$file->id}"))->assertNotFound();
});

it('lets the committee download only with the view permission', function () {
    $tenant = ppdbTenant(slug: 'berkas-izin');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $field = customField($tenant, $period, FieldType::File);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);
    put(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"), [...applicantForm($wave, $path), 'answers' => [$field->id => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertSessionHasNoErrors();

    ppdbMember($tenant, 'staf-tu');
    get(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/berkas/{$field->id}"))->assertOk();

    ppdbMember($tenant, 'guru');
    get(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/berkas/{$field->id}"))->assertForbidden();
});

it('lets an applicant download only their own file', function () {
    [$tenant, $period, $wave, $path] = ppdbOpenSchool('berkas-pemilik');
    $field = customField($tenant, $period, FieldType::File);
    $mine = ppdbAccount($tenant);

    post(FILE_FORM, ownApplicationForm($path, ['answers' => [$field->id => UploadedFile::fake()->create('milikku.pdf', 20, 'application/pdf')]]))->assertSessionHasNoErrors();

    get(FILE_FORM.'/berkas/'.$field->id)->assertOk();
    get(FILE_FORM)->assertRedirect(route('ppdb.account.home'));

    // Someone else's account in the same school sees none of it.
    ppdbAccount($tenant, ['email' => 'orang-lain@contoh.test']);
    get(FILE_FORM.'/berkas/'.$field->id)->assertNotFound();

    // Neither does an account that has not joined a school.
    ppdbAccount(null, ['email' => 'belum-gabung@contoh.test']);
    get(FILE_FORM.'/berkas/'.$field->id)->assertNotFound();

    expect($mine->id)->not->toBeNull();
});

it('removes the files when a registration is cancelled', function () {
    $tenant = ppdbTenant(slug: 'berkas-batal');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $field = customField($tenant, $period, FieldType::File);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);
    put(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"), [...applicantForm($wave, $path), 'answers' => [$field->id => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertSessionHasNoErrors();

    expect(storedFiles())->toHaveCount(1);

    delete(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"))->assertSessionHasNoErrors();

    expect(storedFiles())->toBe([])
        ->and(ppdbSchool($tenant, fn () => ApplicantAnswer::query()->count()))->toBe(0);
});

it('does not leave a file behind when the same field belongs to another period', function () {
    $tenant = ppdbTenant(slug: 'berkas-periode');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $otherPeriod = ppdbPeriod($tenant, ['entry_year' => 2028]);
    $foreign = customField($tenant, $otherPeriod, FieldType::File);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);

    put(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"), [...applicantForm($wave, $path), 'answers' => [$foreign->id => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertSessionHasNoErrors();

    expect(storedFiles())->toBe([])
        ->and(ppdbSchool($tenant, fn () => FormField::query()->whereKey($foreign->id)->exists()))->toBeTrue()
        ->and(ppdbSchool($tenant, fn () => ApplicantAnswer::query()->count()))->toBe(0);
});
