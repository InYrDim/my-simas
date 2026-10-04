<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantNavigation;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Beranda per peran: a teacher sees the classes they look after in the
 * active year, and quick links come only from entries the modules mark.
 */
it('lists a teacher the classes they are homeroom of or teach in the active year', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'home-guru']);
    $account = UserFactory::new()->forTenant($tenant->id)->create(['email' => 'guru@home-guru.test']);

    app(TenantContext::class)->run($tenant->id, function () use ($account): void {
        $account->assignTenantRole('guru');

        $year = AcademicYear::factory()->active()->create();
        $oldYear = AcademicYear::factory()->create();
        $grade = Grade::factory()->create(['name' => 'X', 'sort_order' => 1]);
        $teacher = Teacher::factory()->create(['name' => 'Bu Sari']);
        $teacher->forceFill(['user_id' => $account->id])->save();
        $other = Teacher::factory()->create();

        $homeroom = ClassGroup::factory()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'X 1', 'homeroom_teacher_id' => $teacher->id]);
        $taught = ClassGroup::factory()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'X 2']);
        $old = ClassGroup::factory()->create(['academic_year_id' => $oldYear->id, 'grade_id' => $grade->id, 'name' => 'IX 1']);
        $notMine = ClassGroup::factory()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'X 3']);

        $math = Subject::factory()->create(['name' => 'Matematika']);

        foreach ([$homeroom, $taught, $old] as $class) {
            TeachingAssignment::factory()->create(['class_id' => $class->id, 'subject_id' => $math->id, 'teacher_id' => $teacher->id]);
        }
        TeachingAssignment::factory()->create(['class_id' => $notMine->id, 'subject_id' => $math->id, 'teacher_id' => $other->id]);
    });

    actingAs($account->fresh());

    get(school($tenant->slug, '/beranda'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('classes', [
            ['id' => ClassGroup::withoutGlobalScopes()->where('name', 'X 1')->value('id'), 'name' => 'X 1', 'homeroom' => true, 'subjects' => ['Matematika']],
            ['id' => ClassGroup::withoutGlobalScopes()->where('name', 'X 2')->value('id'), 'name' => 'X 2', 'homeroom' => false, 'subjects' => ['Matematika']],
        ]));
});

it('gives an admin neither shortcuts nor classes', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'home-admin']);
    $admin = UserFactory::new()->forTenant($tenant->id)->create(['email' => 'admin@home-admin.test']);

    app(TenantContext::class)->run($tenant->id, fn () => $admin->assignTenantRole('admin-sekolah'));

    actingAs($admin->fresh());

    get(school($tenant->slug, '/beranda'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('shortcuts', [])
        ->where('classes', []));
});

it('offers a role only the shortcut entries it may see', function () {
    app(TenantNavigation::class)->register('core', [
        ['label' => 'Pintasan Guru', 'icon' => 'star', 'route' => 'core.me.profile', 'permission' => 'core.me.view', 'shortcut' => true, 'order' => 90],
        ['label' => 'Bukan Pintasan', 'icon' => 'star', 'route' => 'core.master.rooms', 'permission' => 'core.master.view', 'order' => 91],
        ['label' => 'Khusus Admin', 'icon' => 'star', 'route' => 'core.manage.import', 'permission' => 'core.master.manage', 'shortcut' => true, 'order' => 92],
    ]);

    $tenant = TenantFactory::new()->create(['slug' => 'home-short']);
    $guru = UserFactory::new()->forTenant($tenant->id)->create(['email' => 'guru@home-short.test']);

    app(TenantContext::class)->run($tenant->id, fn () => $guru->assignTenantRole('guru'));

    actingAs($guru->fresh());

    get(school($tenant->slug, '/beranda'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('shortcuts', [['label' => 'Pintasan Guru', 'href' => '/saya/profil', 'icon' => 'star']]));
});
