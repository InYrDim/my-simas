<?php

namespace Modules\Ppdb\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Modules\Ppdb\App\Domain\Models\FormField;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * Formulir: the builder page and what saving it does — adding, changing,
 * ordering, archiving and removing fields, and what is refused.
 */

/**
 * The period's form as the builder sends it back: every field in order.
 *
 * @return list<array<string, mixed>>
 */
function builderRows(Tenant $tenant, AdmissionPeriod $period): array
{
    return ppdbSchool($tenant, fn () => $period->fields()->get()->map(fn (FormField $field): array => [
        'id' => $field->id,
        'type' => $field->type->value,
        'label' => $field->label,
        'help' => $field->help,
        'required' => $field->required,
        'archived' => $field->isArchived(),
        'options' => $field->options ?? [],
        'rules' => $field->rules ?? [],
    ])->all());
}

/**
 * A custom field row the builder would send for a new field.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newFieldRow(array $overrides = []): array
{
    return [
        'id' => null,
        'type' => 'text',
        'label' => 'Hobi',
        'help' => null,
        'required' => false,
        'archived' => false,
        'options' => [],
        'rules' => ['max_length' => null, 'format' => 'free'],
        ...$overrides,
    ];
}

function builderUrl(Tenant $tenant, ?AdmissionPeriod $period = null): string
{
    return school($tenant->slug, '/ppdb/formulir'.($period === null ? '' : "/{$period->id}"));
}

it('shows the builder the form of the chosen period, in order, with what the page needs', function () {
    $tenant = ppdbTenant(slug: 'form-tampil');
    [$period] = ppdbSetup($tenant);
    ppdbPath($tenant, $period, ['name' => 'Prestasi', 'sort_order' => 1]);
    ppdbSchool($tenant, fn () => FormField::factory()->ofType(FieldType::Select)->withOptions(['A', 'B'])->create(['period_id' => $period->id, 'label' => 'Kelas']));

    get(builderUrl($tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/FormBuilder')
        ->where('selected.id', $period->id)
        ->where('selected.editable', true)
        ->has('fields', 11)
        ->where('fields.0.key', 'path_id')
        ->where('fields.0.locked', true)
        ->where('fields.3.key', 'nisn')
        ->where('fields.3.locked', false)
        ->where('fields.10.label', 'Kelas')
        ->where('fields.10.options', ['A', 'B'])
        ->where('fields.10.hasAnswers', false)
        ->where('paths', fn ($paths) => collect($paths)->pluck('label')->all() === ['Zonasi', 'Prestasi'])
        ->where('types', fn ($types) => collect($types)->pluck('value')->all() === ['text', 'paragraph', 'number', 'date', 'select', 'checkboxes', 'file', 'section'])
    );
});

it('tells a school with no period to make one first', function () {
    $tenant = ppdbTenant(slug: 'form-kosong');

    get(builderUrl($tenant))->assertOk()->assertInertia(fn (Assert $page) => $page->where('selected', null)->where('fields', []));
});

it('lets only those who manage the settings open or save the builder', function () {
    $tenant = ppdbTenant(slug: 'form-izin', role: 'staf-tu');
    [$period] = ppdbSetup($tenant);

    get(builderUrl($tenant))->assertForbidden();
    put(builderUrl($tenant, $period), ['fields' => builderRows($tenant, $period)])->assertForbidden();

    ppdbMember($tenant, 'guru');

    get(builderUrl($tenant))->assertForbidden();
});

it('adds a custom field and puts every field in the order the page sent', function () {
    $tenant = ppdbTenant(slug: 'form-simpan');
    [$period] = ppdbSetup($tenant);
    $rows = builderRows($tenant, $period);

    // The new field goes first, then the built-in ones in reverse.
    $reordered = [newFieldRow(['label' => 'Hobi', 'help' => 'Satu kegiatan', 'rules' => ['max_length' => 40, 'format' => 'free']]), ...array_reverse($rows)];

    put(builderUrl($tenant, $period), ['fields' => $reordered])->assertSessionHasNoErrors()->assertSessionHas('status');

    $fields = ppdbSchool($tenant, fn () => $period->fields()->get());

    expect($fields)->toHaveCount(11)
        ->and($fields->first()->label)->toBe('Hobi')
        ->and($fields->first()->type)->toBe(FieldType::Text)
        ->and($fields->first()->key)->toBeNull()
        ->and($fields->first()->help)->toBe('Satu kegiatan')
        ->and($fields->first()->rules)->toBe(['max_length' => 40, 'format' => 'free'])
        ->and($fields->first()->tenant_id)->toBe($tenant->id)
        ->and($fields->pluck('key')->filter()->values()->all())->toBe(array_reverse(['path_id', 'name', 'gender', 'nisn', 'birth_place', 'birth_date', 'origin_school', 'address', 'guardian_name', 'guardian_phone']))
        ->and($fields->pluck('sort_order')->all())->toBe(range(0, 10));
});

it('changes a built-in field only by its help, whether it is required and whether it is archived', function () {
    $tenant = ppdbTenant(slug: 'form-bawaan');
    [$period] = ppdbSetup($tenant);
    $rows = builderRows($tenant, $period);

    foreach ($rows as &$row) {
        if ($row['label'] === 'NISN') {
            $row['label'] = 'Nomor Induk';
            $row['help'] = '10 angka';
            $row['required'] = true;
            $row['archived'] = true;
        }
    }
    unset($row);

    put(builderUrl($tenant, $period), ['fields' => $rows])->assertSessionHasNoErrors();

    $nisn = ppdbSchool($tenant, fn () => $period->fields()->where('key', 'nisn')->sole());

    expect($nisn->label)->toBe('NISN')
        ->and($nisn->help)->toBe('10 angka')
        ->and($nisn->required)->toBeTrue()
        ->and($nisn->isArchived())->toBeTrue();

    // Restoring it clears the archive.
    foreach ($rows as &$row) {
        $row['archived'] = false;
    }
    unset($row);

    put(builderUrl($tenant, $period), ['fields' => $rows])->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => $period->fields()->where('key', 'nisn')->sole()->isArchived()))->toBeFalse();
});

it('never archives or relaxes the path, name and gender', function () {
    $tenant = ppdbTenant(slug: 'form-terkunci');
    [$period] = ppdbSetup($tenant);
    $rows = builderRows($tenant, $period);

    $rows[1]['archived'] = true;

    put(builderUrl($tenant, $period), ['fields' => $rows])->assertSessionHasErrors('fields.1.archived');

    $rows[1]['archived'] = false;
    $rows[1]['required'] = false;

    put(builderUrl($tenant, $period), ['fields' => $rows])->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => $period->fields()->where('key', 'name')->sole()->required))->toBeTrue();
});

it('refuses a field that cannot be saved and saves nothing of the rest', function (array $row, string $errorKey) {
    $tenant = ppdbTenant(slug: 'form-tolak');
    [$period] = ppdbSetup($tenant);
    $rows = [...builderRows($tenant, $period), newFieldRow(['label' => 'Baik']), newFieldRow($row)];
    $rows[0]['help'] = 'Tidak boleh tersimpan';

    put(builderUrl($tenant, $period), ['fields' => $rows])->assertSessionHasErrors($errorKey);

    expect(ppdbSchool($tenant, fn () => $period->fields()->count()))->toBe(10)
        ->and(ppdbSchool($tenant, fn () => $period->fields()->first()->help))->toBeNull();
})->with([
    'no label' => [['label' => ''], 'fields.11.label'],
    'a type that does not exist' => [['type' => 'radio'], 'fields.11.type'],
    'a select without options' => [['type' => 'select', 'options' => [], 'rules' => []], 'fields.11.options'],
    'options that repeat' => [['type' => 'checkboxes', 'options' => ['Ya', 'ya'], 'rules' => []], 'fields.11.options'],
    'too many options' => [['type' => 'select', 'options' => array_map(fn (int $n): string => "Opsi {$n}", range(1, 31)), 'rules' => []], 'fields.11.options'],
    'a text length out of range' => [['rules' => ['max_length' => 900, 'format' => 'free']], 'fields.11.rules.max_length'],
    'a text format that does not exist' => [['rules' => ['max_length' => null, 'format' => 'regex']], 'fields.11.rules.format'],
    'a number range turned around' => [['type' => 'number', 'rules' => ['min' => 10, 'max' => 5]], 'fields.11.rules.max'],
    'a file size out of range' => [['type' => 'file', 'rules' => ['max_size_kb' => 9000, 'kinds' => ['pdf']]], 'fields.11.rules.max_size_kb'],
    'a file field accepting no kind' => [['type' => 'file', 'rules' => ['max_size_kb' => 1000, 'kinds' => []]], 'fields.11.rules.kinds'],
]);

it('refuses a list that leaves a field out, names an unknown one, or is too long', function () {
    $tenant = ppdbTenant(slug: 'form-lengkap');
    [$period] = ppdbSetup($tenant);
    $rows = builderRows($tenant, $period);

    $missing = $rows;
    array_pop($missing);

    put(builderUrl($tenant, $period), ['fields' => $missing])->assertSessionHasErrors('form');

    $unknown = $rows;
    $unknown[3]['id'] = 999999;

    put(builderUrl($tenant, $period), ['fields' => $unknown])->assertSessionHasErrors('fields.3.id');

    $tooMany = [...$rows, ...array_map(fn (int $n): array => newFieldRow(['label' => "Tambahan {$n}"]), range(1, 51))];

    put(builderUrl($tenant, $period), ['fields' => $tooMany])->assertSessionHasErrors('fields');

    expect(ppdbSchool($tenant, fn () => $period->fields()->count()))->toBe(10);
});

it('keeps another period\'s field out of this period\'s form', function () {
    $tenant = ppdbTenant(slug: 'form-periode-lain');
    [$period] = ppdbSetup($tenant);
    $other = ppdbPeriod($tenant, ['entry_year' => 2028]);
    $rows = builderRows($tenant, $period);
    $rows[3]['id'] = ppdbSchool($tenant, fn () => $other->fields()->where('key', 'nisn')->sole()->id);

    put(builderUrl($tenant, $period), ['fields' => $rows])->assertSessionHasErrors('fields.3.id');
});

it('locks the type of a field that has answers but lets its label, options and archive change', function () {
    $tenant = ppdbTenant(slug: 'form-jawaban');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $field = ppdbSchool($tenant, fn () => FormField::factory()->ofType(FieldType::Select)->withOptions(['A', 'B'])->create(['period_id' => $period->id, 'label' => 'Kelas']));
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);
    ppdbSchool($tenant, fn () => ApplicantAnswer::factory()->create(['applicant_id' => $applicant->id, 'field_id' => $field->id, 'value' => 'A']));

    get(builderUrl($tenant))->assertInertia(fn (Assert $page) => $page->where('fields.10.hasAnswers', true));

    $rows = builderRows($tenant, $period);
    $rows[10]['type'] = 'text';

    put(builderUrl($tenant, $period), ['fields' => $rows])->assertSessionHasErrors('fields.10.type');

    $rows[10]['type'] = 'select';
    $rows[10]['label'] = 'Kelas yang dipilih';
    $rows[10]['options'] = ['A', 'B', 'C'];
    $rows[10]['archived'] = true;

    put(builderUrl($tenant, $period), ['fields' => $rows])->assertSessionHasNoErrors();

    $fresh = ppdbSchool($tenant, fn () => $field->fresh());

    expect($fresh->label)->toBe('Kelas yang dipilih')
        ->and($fresh->options)->toBe(['A', 'B', 'C'])
        ->and($fresh->isArchived())->toBeTrue()
        ->and(ppdbSchool($tenant, fn () => ApplicantAnswer::query()->count()))->toBe(1);
});

it('removes a custom field only while nobody has answered it', function () {
    $tenant = ppdbTenant(slug: 'form-hapus');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $free = ppdbSchool($tenant, fn () => FormField::factory()->create(['period_id' => $period->id, 'label' => 'Kosong']));
    $answered = ppdbSchool($tenant, fn () => FormField::factory()->create(['period_id' => $period->id, 'label' => 'Terjawab']));
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);
    ppdbSchool($tenant, fn () => ApplicantAnswer::factory()->create(['applicant_id' => $applicant->id, 'field_id' => $answered->id]));
    $builtin = ppdbSchool($tenant, fn () => $period->fields()->where('key', 'nisn')->sole());

    delete(school($tenant->slug, "/ppdb/formulir/kolom/{$answered->id}"))->assertSessionHasErrors('form');
    delete(school($tenant->slug, "/ppdb/formulir/kolom/{$builtin->id}"))->assertSessionHasErrors('form');
    delete(school($tenant->slug, "/ppdb/formulir/kolom/{$free->id}"))->assertSessionHasNoErrors()->assertSessionHas('status');

    expect(ppdbSchool($tenant, fn () => FormField::query()->whereKey($free->id)->exists()))->toBeFalse()
        ->and(ppdbSchool($tenant, fn () => FormField::query()->whereKey($answered->id)->exists()))->toBeTrue()
        ->and(ppdbSchool($tenant, fn () => FormField::query()->whereKey($builtin->id)->exists()))->toBeTrue();
});

it('shows a closed period\'s form read-only and refuses to change or shrink it', function () {
    $tenant = ppdbTenant(slug: 'form-tutup');
    [$period] = ppdbSetup($tenant);
    $custom = ppdbSchool($tenant, fn () => FormField::factory()->create(['period_id' => $period->id]));
    ppdbSchool($tenant, fn () => $period->update(['status' => PeriodStatus::Closed]));

    get(builderUrl($tenant))->assertInertia(fn (Assert $page) => $page->where('selected.editable', false));

    $rows = builderRows($tenant, $period);
    $rows[3]['required'] = true;

    put(builderUrl($tenant, $period), ['fields' => $rows])->assertSessionHasErrors('form');
    delete(school($tenant->slug, "/ppdb/formulir/kolom/{$custom->id}"))->assertSessionHasErrors('form');

    expect(ppdbSchool($tenant, fn () => FormField::query()->whereKey($custom->id)->exists()))->toBeTrue()
        ->and(ppdbSchool($tenant, fn () => $period->fields()->where('key', 'nisn')->sole()->required))->toBeFalse();
});

it('does not reach another school\'s period or field', function () {
    $tenant = ppdbTenant(slug: 'form-sekolah-a');
    [$period] = ppdbSetup($tenant);
    $field = ppdbSchool($tenant, fn () => FormField::factory()->create(['period_id' => $period->id]));
    $rows = builderRows($tenant, $period);

    $other = ppdbTenant(slug: 'form-sekolah-b');

    put(school($other->slug, "/ppdb/formulir/{$period->id}"), ['fields' => $rows])->assertNotFound();
    delete(school($other->slug, "/ppdb/formulir/kolom/{$field->id}"))->assertNotFound();

    expect(ppdbSchool($tenant, fn () => FormField::query()->whereKey($field->id)->exists()))->toBeTrue();
});
