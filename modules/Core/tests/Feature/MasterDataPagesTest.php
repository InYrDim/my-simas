<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Master-data and data-action mockup pages: every route renders its Inertia component for
 * a signed-in school user, and guests are sent to the login.
 */
function masterTenant(): string
{
    $tenant = TenantFactory::new()->create(['slug' => 'master-mock']);

    $user = UserFactory::new()->forTenant($tenant->id)->create(['email' => 'admin@master-mock.test']);

    app(TenantContext::class)->run($tenant->id, fn () => $user->assignTenantRole('admin-sekolah'));

    actingAs($user);

    return $tenant->slug;
}

dataset('masterPages', [
    'school' => ['/master/sekolah', 'Core/Master/School/Show'],
    'years' => ['/master/tahun-ajaran', 'Core/Master/AcademicYears/Index'],
    'grades' => ['/master/tingkat-jurusan', 'Core/Master/Grades/Index'],
    'classes' => ['/master/kelas', 'Core/Master/Classes/Index'],
    'class' => ['/master/kelas/1', 'Core/Master/Classes/Show'],
    'subjects' => ['/master/mata-pelajaran', 'Core/Master/Subjects/Index'],
    'teachers' => ['/master/guru', 'Core/Master/Teachers/Index'],
    'teacher' => ['/master/guru/2', 'Core/Master/Teachers/Show'],
    'students' => ['/master/siswa', 'Core/Master/Students/Index'],
    'student' => ['/master/siswa/1', 'Core/Master/Students/Show'],
    'rooms' => ['/master/ruangan', 'Core/Master/Rooms/Index'],
    'extracurriculars' => ['/master/ekstrakurikuler', 'Core/Master/Extracurriculars/Index'],
    'extracurricular' => ['/master/ekstrakurikuler/1', 'Core/Master/Extracurriculars/Show'],
    'placement' => ['/akademik/penempatan', 'Core/Academic/Placement/Index'],
    'assignments' => ['/akademik/pengampu', 'Core/Academic/Assignments/Index'],
    'homerooms' => ['/akademik/wali-kelas', 'Core/Academic/Homerooms/Index'],
    'periods' => ['/akademik/jam-pelajaran', 'Core/Academic/Periods/Index'],
    'calendar' => ['/akademik/kalender', 'Core/Academic/Calendar/Index'],
    'import' => ['/kelola/impor', 'Core/Manage/Import/Index'],
]);

it('renders each master page for a school user', function (string $path, string $component) {
    $slug = masterTenant();

    get(school($slug, $path))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component($component)
        ->has('school.levelLabel')
    );
})->with('masterPages');

it('redirects guests away from master pages', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'master-guest']);

    get(school($tenant->slug, '/master/kelas'))->assertRedirect();
});

it('returns 404 for an unknown record', function () {
    $slug = masterTenant();

    get(school($slug, '/master/guru/999'))->assertNotFound();
});

it('previews each jenjang and keeps it for the session', function () {
    $slug = masterTenant();

    get(school($slug, '/master/sekolah?jenjang=smk'))->assertInertia(fn (Assert $page) => $page
        ->where('school.level', 'smk')
        ->where('school.hasMajors', true)
    );

    get(school($slug, '/master/tingkat-jurusan'))->assertInertia(fn (Assert $page) => $page
        ->where('school.level', 'smk')
        ->has('majors', 3)
    );

    get(school($slug, '/master/sekolah?jenjang=sd'))->assertInertia(fn (Assert $page) => $page
        ->where('school.hasMajors', false)
        ->where('school.homeroomLabel', 'Guru Kelas')
    );
});

it('splits the sidebar into base data, academic management and import', function () {
    $slug = masterTenant();

    get(school($slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav.1.label', 'Master Data')
        ->has('tenantNav.1.children', 9)
        ->where('tenantNav.1.children.0.href', '/master/sekolah')
        ->where('tenantNav.2.label', 'Akademik')
        ->where('tenantNav.2.children', fn ($children) => collect($children)->pluck('href')->all() === [
            '/akademik/penempatan', '/akademik/pengampu', '/akademik/wali-kelas',
            '/akademik/jam-pelajaran', '/akademik/kalender',
        ])
        ->where('tenantNav.3.label', 'Impor Data')
        ->where('tenantNav.3.href', '/kelola/impor')
        ->has('tenantNav.3.children', 0)
    );
});
