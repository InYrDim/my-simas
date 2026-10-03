<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

it('lists the classes of the active year with their homeroom teacher and every teacher', function () {
    $tenant = schoolAs('hr-list');
    $class = classIn($tenant, ['name' => 'X 1']);
    $teacher = inSchool($tenant, fn () => Teacher::factory()->create(['name' => 'Bu Rina']));
    inSchool($tenant, fn () => $class->forceFill(['homeroom_teacher_id' => $teacher->id])->save());

    get(school($tenant->slug, '/akademik/wali-kelas'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Core/Academic/Homerooms/Index')
        ->has('classes', 1)
        ->where('classes.0.name', 'X 1')
        ->where('classes.0.homeroomId', $teacher->id)
        ->has('teachers', 1)
    );
});

it('saves the homeroom teachers and shows them on the class list', function () {
    $tenant = schoolAs('hr-save');
    $class = classIn($tenant, ['name' => 'X 1']);
    $teacher = inSchool($tenant, fn () => Teacher::factory()->create(['name' => 'Bu Rina']));

    put(school($tenant->slug, '/akademik/wali-kelas'), ['homerooms' => [$class->id => $teacher->id]])
        ->assertRedirect();

    expect(inSchool($tenant, fn () => $class->fresh()->homeroom_teacher_id))->toBe($teacher->id);

    get(school($tenant->slug, '/master/kelas'))->assertInertia(fn (Assert $page) => $page
        ->where('classes.0.homeroom', 'Bu Rina'));
});

it('clears a homeroom teacher with the none sentinel', function () {
    $tenant = schoolAs('hr-clear');
    $teacher = inSchool($tenant, fn () => Teacher::factory()->create());
    $class = classIn($tenant);
    inSchool($tenant, fn () => $class->forceFill(['homeroom_teacher_id' => $teacher->id])->save());

    put(school($tenant->slug, '/akademik/wali-kelas'), ['homerooms' => [$class->id => 'none']])
        ->assertRedirect();

    expect(inSchool($tenant, fn () => $class->fresh()->homeroom_teacher_id))->toBeNull();
});

it('lets one teacher hold several classes', function () {
    $tenant = schoolAs('hr-multi');
    $first = classIn($tenant, ['name' => 'X 1']);
    $second = classIn($tenant, ['name' => 'X 2']);
    $teacher = inSchool($tenant, fn () => Teacher::factory()->create());

    put(school($tenant->slug, '/akademik/wali-kelas'), ['homerooms' => [$first->id => $teacher->id, $second->id => $teacher->id]])
        ->assertRedirect();

    expect(inSchool($tenant, fn () => ClassGroup::query()->where('homeroom_teacher_id', $teacher->id)->count()))->toBe(2);
});

it('refuses a teacher of another school and stores nothing', function () {
    $tenant = schoolAs('hr-own');
    $class = classIn($tenant);
    $other = TenantFactory::new()->create(['slug' => 'hr-other']);
    $foreign = inSchool($other, fn () => Teacher::factory()->create());

    put(school($tenant->slug, '/akademik/wali-kelas'), ['homerooms' => [$class->id => $foreign->id]])
        ->assertSessionHasErrors("homerooms.{$class->id}");

    expect(inSchool($tenant, fn () => $class->fresh()->homeroom_teacher_id))->toBeNull();
});

it('refuses a class of another school and leaves it untouched', function () {
    $tenant = schoolAs('hr-mine');
    classIn($tenant);
    $teacher = inSchool($tenant, fn () => Teacher::factory()->create());
    $other = TenantFactory::new()->create(['slug' => 'hr-theirs']);
    $foreignClass = classIn($other);

    put(school($tenant->slug, '/akademik/wali-kelas'), ['homerooms' => [$foreignClass->id => $teacher->id]])
        ->assertSessionHasErrors('homerooms');

    expect(inSchool($other, fn () => $foreignClass->fresh()->homeroom_teacher_id))->toBeNull();
});

it('refuses a class outside the active academic year', function () {
    $tenant = schoolAs('hr-year');
    $draft = yearWithSemesters($tenant, 'draft', '2030-07-13');
    $class = classIn($tenant, ['academic_year_id' => $draft->id]);
    $teacher = inSchool($tenant, fn () => Teacher::factory()->create());

    put(school($tenant->slug, '/akademik/wali-kelas'), ['homerooms' => [$class->id => $teacher->id]])
        ->assertSessionHasErrors('homerooms');

    expect(inSchool($tenant, fn () => $class->fresh()->homeroom_teacher_id))->toBeNull();
});

it('forbids a teacher role from saving and from the editor page', function () {
    $tenant = schoolAs('hr-guru', 'guru');
    $class = classIn($tenant);
    $teacher = inSchool($tenant, fn () => Teacher::factory()->create());

    get(school($tenant->slug, '/akademik/wali-kelas'))->assertForbidden();

    put(school($tenant->slug, '/akademik/wali-kelas'), ['homerooms' => [$class->id => $teacher->id]])
        ->assertForbidden();

    expect(inSchool($tenant, fn () => $class->fresh()->homeroom_teacher_id))->toBeNull();
});

it('redirects a guest who tries to save', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'hr-guest']);

    put(school($tenant->slug, '/akademik/wali-kelas'), ['homerooms' => []])->assertRedirect();
});
