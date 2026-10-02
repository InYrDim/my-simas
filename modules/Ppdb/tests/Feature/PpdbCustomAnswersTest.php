<?php

namespace Modules\Ppdb\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

beforeEach(function () {
    // 10 February 2027, 10:00 in Jakarta: inside the default wave (1 Jan – 31 Mar).
    $this->travelTo('2027-02-10 03:00:00');
});

/*
 * Custom fields in use: what the committee and the applicant send for them
 * is validated by the field's own rules, stored as answers, shown back, and
 * never leaks across fields that are archived, other periods or other
 * schools.
 */

it('checks an answer by the rules of its field', function (FieldType $type, array $options, array $rules, mixed $bad, mixed $good, mixed $stored) {
    $tenant = ppdbTenant(slug: 'jawab-aturan');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $field = customField($tenant, $period, $type, $options, $rules, ['required' => true]);
    $url = school($tenant->slug, '/ppdb/pendaftar');

    post($url, [...applicantForm($wave, $path), 'answers' => [$field->id => $bad]])->assertSessionHasErrors("answers.{$field->id}");
    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(0);

    post($url, [...applicantForm($wave, $path), 'answers' => [$field->id => $good]])->assertSessionHasNoErrors();

    $applicant = ppdbSchool($tenant, fn () => Applicant::query()->sole());

    expect(storedAnswer($tenant, $applicant, $field))->toBe($stored);
})->with([
    'text longer than allowed' => [FieldType::Text, [], ['max_length' => 5, 'format' => 'free'], 'abcdefg', 'abcde', 'abcde'],
    'text that is not only digits' => [FieldType::Text, [], ['max_length' => null, 'format' => 'digits'], 'abc1', '12345', '12345'],
    'text that is not an email' => [FieldType::Text, [], ['max_length' => null, 'format' => 'email'], 'bukan-email', 'a@b.id', 'a@b.id'],
    'text that is not a phone number' => [FieldType::Text, [], ['max_length' => null, 'format' => 'phone'], 'abc', '+62 812-3456', '+62 812-3456'],
    'paragraph longer than allowed' => [FieldType::Paragraph, [], ['max_length' => 10], str_repeat('a', 11), 'cukup', 'cukup'],
    'number above the maximum' => [FieldType::Number, [], ['min' => 1, 'max' => 10], '11', '5', '5'],
    'number that is not a number' => [FieldType::Number, [], ['min' => null, 'max' => null], 'lima', '5.5', '5.5'],
    'date in the future when not allowed' => [FieldType::Date, [], ['allow_future' => false], '2099-01-01', '2012-05-04', '2012-05-04'],
    'date in the wrong format' => [FieldType::Date, [], ['allow_future' => true], '04-05-2012', '2099-01-01', '2099-01-01'],
    'select answer that is not an option' => [FieldType::Select, ['A', 'B'], [], 'C', 'A', 'A'],
    'checkboxes sent as plain text' => [FieldType::Checkboxes, ['A', 'B'], [], 'A', ['A', 'B'], '["A","B"]'],
]);

it('refuses a checkboxes choice that is not one of the options', function () {
    $tenant = ppdbTenant(slug: 'jawab-pilihan');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $field = customField($tenant, $period, FieldType::Checkboxes, ['A', 'B']);

    post(school($tenant->slug, '/ppdb/pendaftar'), [...applicantForm($wave, $path), 'answers' => [$field->id => ['A', 'Z']]])
        ->assertSessionHasErrors("answers.{$field->id}.1");

    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(0);
});

it('asks a required custom field and accepts an optional one left empty', function () {
    $tenant = ppdbTenant(slug: 'jawab-wajib');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $required = customField($tenant, $period, FieldType::Text, attributes: ['required' => true, 'label' => 'Wajib']);
    $optional = customField($tenant, $period, FieldType::Text, attributes: ['label' => 'Boleh kosong']);
    $url = school($tenant->slug, '/ppdb/pendaftar');

    post($url, [...applicantForm($wave, $path), 'answers' => [$required->id => '', $optional->id => '']])->assertSessionHasErrors("answers.{$required->id}");

    post($url, [...applicantForm($wave, $path), 'answers' => [$required->id => 'Isi', $optional->id => '']])->assertSessionHasNoErrors();

    $applicant = ppdbSchool($tenant, fn () => Applicant::query()->sole());

    expect(storedAnswer($tenant, $applicant, $required))->toBe('Isi')
        ->and(storedAnswer($tenant, $applicant, $optional))->toBeNull()
        ->and(ppdbSchool($tenant, fn () => ApplicantAnswer::query()->count()))->toBe(1);
});

it('names the field by its label in the error message', function () {
    $tenant = ppdbTenant(slug: 'jawab-pesan');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $field = customField($tenant, $period, FieldType::Text, attributes: ['required' => true, 'label' => 'Hobi utama']);

    post(school($tenant->slug, '/ppdb/pendaftar'), applicantForm($wave, $path))
        ->assertSessionHasErrors(["answers.{$field->id}" => 'Hobi utama wajib diisi.']);
});

it('drops answers to fields that are archived, a section, from another period or another school', function () {
    $tenant = ppdbTenant(slug: 'jawab-buang');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $archived = customField($tenant, $period, FieldType::Text, attributes: ['archived_at' => now(), 'label' => 'Lama']);
    $section = customField($tenant, $period, FieldType::Section);
    $otherPeriod = ppdbPeriod($tenant, ['entry_year' => 2028]);
    $ofOtherPeriod = customField($tenant, $otherPeriod, FieldType::Text);
    $elsewhere = ppdbTenant(slug: 'jawab-buang-lain');
    [$elsewherePeriod] = ppdbSetup($elsewhere);
    $ofOtherSchool = customField($elsewhere, $elsewherePeriod, FieldType::Text);
    ppdbMember($tenant, 'admin-sekolah');

    post(school($tenant->slug, '/ppdb/pendaftar'), [
        ...applicantForm($wave, $path),
        'answers' => [$archived->id => 'x', $section->id => 'x', $ofOtherPeriod->id => 'x', $ofOtherSchool->id => 'x', 999999 => 'x'],
    ])->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => ApplicantAnswer::query()->count()))->toBe(0)
        ->and(ppdbSchool($elsewhere, fn () => ApplicantAnswer::query()->count()))->toBe(0);
});

it('changes, empties and leaves alone the answers the committee edits', function () {
    $tenant = ppdbTenant(slug: 'jawab-ubah');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $first = customField($tenant, $period, FieldType::Text, attributes: ['label' => 'Pertama']);
    $second = customField($tenant, $period, FieldType::Text, attributes: ['label' => 'Kedua']);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);
    ppdbSchool($tenant, fn () => ApplicantAnswer::factory()->create(['applicant_id' => $applicant->id, 'field_id' => $first->id, 'value' => 'lama']));
    ppdbSchool($tenant, fn () => ApplicantAnswer::factory()->create(['applicant_id' => $applicant->id, 'field_id' => $second->id, 'value' => 'tetap']));
    $url = school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}");

    // The first is changed; the second is not sent at all.
    put($url, [...applicantForm($wave, $path), 'answers' => [$first->id => 'baru']])->assertSessionHasNoErrors();

    expect(storedAnswer($tenant, $applicant, $first))->toBe('baru')
        ->and(storedAnswer($tenant, $applicant, $second))->toBe('tetap');

    // Sent empty: removed.
    put($url, [...applicantForm($wave, $path), 'answers' => [$first->id => '', $second->id => '']])->assertSessionHasNoErrors();

    expect(storedAnswer($tenant, $applicant, $first))->toBeNull()
        ->and(storedAnswer($tenant, $applicant, $second))->toBeNull();
});

it('shows the committee every field and the answers, archived ones included', function () {
    $tenant = ppdbTenant(slug: 'jawab-detail');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $hobby = customField($tenant, $period, FieldType::Checkboxes, ['Membaca', 'Olahraga'], attributes: ['label' => 'Hobi']);
    $old = customField($tenant, $period, FieldType::Text, attributes: ['label' => 'Dulu', 'archived_at' => now()]);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);
    ppdbSchool($tenant, fn () => ApplicantAnswer::factory()->create(['applicant_id' => $applicant->id, 'field_id' => $hobby->id, 'value' => '["Membaca","Olahraga"]']));
    ppdbSchool($tenant, fn () => ApplicantAnswer::factory()->create(['applicant_id' => $applicant->id, 'field_id' => $old->id, 'value' => 'jawaban lama']));

    get(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('answers', [(string) $hobby->id => ['Membaca', 'Olahraga'], (string) $old->id => 'jawaban lama'])
        ->where('fields', fn ($fields) => collect($fields)->pluck('label')->slice(-2)->values()->all() === ['Hobi', 'Dulu'])
        ->where('fields', fn ($fields) => collect($fields)->last()['archived'] === true)
    );
});

it('draws the committee\'s new applicant form in the order the school set', function () {
    $tenant = ppdbTenant(slug: 'jawab-urutan');
    [$period] = ppdbSetup($tenant);
    $first = customField($tenant, $period, FieldType::Text, attributes: ['label' => 'Di depan', 'sort_order' => -1]);

    get(school($tenant->slug, '/ppdb/pendaftar/tambah'))->assertInertia(fn (Assert $page) => $page
        ->where('fields.0.id', $first->id)
        ->where('fields.1.key', 'path_id')
    );
});

it('removes the answers when a registration is cancelled', function () {
    $tenant = ppdbTenant(slug: 'jawab-batal');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $field = customField($tenant, $period, FieldType::Text);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);
    ppdbSchool($tenant, fn () => ApplicantAnswer::factory()->create(['applicant_id' => $applicant->id, 'field_id' => $field->id]));

    delete(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"))->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => ApplicantAnswer::query()->count()))->toBe(0);
});

it('asks the applicant the custom fields of the running period and saves their answers', function () {
    [$tenant, $period, , $path] = ppdbOpenSchool('jawab-calon');
    $hobby = customField($tenant, $period, FieldType::Checkboxes, ['Membaca', 'Olahraga'], attributes: ['label' => 'Hobi', 'required' => true]);
    $class = customField($tenant, $period, FieldType::Select, ['Reguler', 'Unggulan'], attributes: ['label' => 'Program']);
    ppdbAccount($tenant);

    get('http://localhost/calon-siswa/formulir')->assertInertia(fn (Assert $page) => $page
        ->where('fields', fn ($fields) => collect($fields)->pluck('label')->slice(-2)->values()->all() === ['Hobi', 'Program'])
        ->where('answers', [])
    );

    post('http://localhost/calon-siswa/formulir', ownApplicationForm($path))->assertSessionHasErrors("answers.{$hobby->id}");

    post('http://localhost/calon-siswa/formulir', ownApplicationForm($path, ['answers' => [$hobby->id => ['Membaca'], $class->id => 'Unggulan']]))
        ->assertSessionHasNoErrors();

    $applicant = ppdbSchool($tenant, fn () => Applicant::query()->sole());

    expect(storedAnswer($tenant, $applicant, $hobby))->toBe('["Membaca"]')
        ->and(storedAnswer($tenant, $applicant, $class))->toBe('Unggulan');
});

it('lets the applicant correct their answers and sends the registration back for checking', function () {
    [$tenant, $period, $wave, $path] = ppdbOpenSchool('jawab-perbaikan');
    $field = customField($tenant, $period, FieldType::Text, attributes: ['label' => 'Hobi', 'required' => true]);
    $account = ppdbAccount($tenant);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path, [
        'account_id' => $account->id,
        'status' => ApplicantStatus::Revision,
        'verification_note' => 'Lengkapi hobi.',
    ]);
    ppdbSchool($tenant, fn () => ApplicantAnswer::factory()->create(['applicant_id' => $applicant->id, 'field_id' => $field->id, 'value' => 'lama']));

    get('http://localhost/calon-siswa/formulir')->assertInertia(fn (Assert $page) => $page->where('answers', [(string) $field->id => 'lama']));

    put('http://localhost/calon-siswa/formulir', ownApplicationForm($path, ['answers' => [$field->id => '']]))->assertSessionHasErrors("answers.{$field->id}");

    put('http://localhost/calon-siswa/formulir', ownApplicationForm($path, ['answers' => [$field->id => 'baru']]))->assertSessionHasNoErrors();

    expect(storedAnswer($tenant, $applicant, $field))->toBe('baru')
        ->and(ppdbSchool($tenant, fn () => $applicant->fresh()->status))->toBe(ApplicantStatus::Submitted);
});

it('never lets an applicant write an answer in another school\'s field or into another applicant', function () {
    [$tenant, $period, $wave, $path] = ppdbOpenSchool('jawab-isolasi-a');
    $mine = customField($tenant, $period, FieldType::Text, attributes: ['label' => 'Milik sekolah ini']);
    [$other, $otherPeriod] = ppdbOpenSchool('jawab-isolasi-b');
    $theirs = customField($other, $otherPeriod, FieldType::Text);
    $someone = ppdbApplicant($tenant, $period, $wave, $path);
    ppdbAccount($tenant);

    post('http://localhost/calon-siswa/formulir', ownApplicationForm($path, ['answers' => [$mine->id => 'ok', $theirs->id => 'bocor']]))
        ->assertSessionHasNoErrors();

    $applicant = ppdbSchool($tenant, fn () => Applicant::query()->where('account_id', '!=', null)->sole());

    expect(storedAnswer($tenant, $applicant, $mine))->toBe('ok')
        ->and(ppdbSchool($tenant, fn () => ApplicantAnswer::query()->where('field_id', $theirs->id)->count()))->toBe(0)
        ->and(ppdbSchool($other, fn () => ApplicantAnswer::query()->count()))->toBe(0)
        ->and(ppdbSchool($tenant, fn () => ApplicantAnswer::query()->where('applicant_id', $someone->id)->count()))->toBe(0);
});
