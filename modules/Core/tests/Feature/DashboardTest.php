<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Carbon;
use Modules\Core\App\Contracts\DashboardRegistry;
use Modules\Core\App\Contracts\DashboardWidgetProvider;
use Modules\Core\App\Contracts\DTOs\DashboardWidget;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Core\App\Domain\Models\TimetableEntry;
use Modules\Core\App\Infrastructure\Dashboard\DefaultDashboardRegistry;
use Modules\Platform\App\Domain\Models\Tenant;
use RuntimeException;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

/*
 * The registry behind the Beranda: what a module registers through Core's
 * contract, and what the signed-in user gets to see of it.
 */
final class OpenDashboardWidgets implements DashboardWidgetProvider
{
    public function widgets(): array
    {
        return [
            new DashboardWidget('late', DashboardWidget::KIND_STAT, DashboardWidget::SLOT_FIGURES, 'Terlambat', ['value' => 3], order: 20),
            new DashboardWidget('early', DashboardWidget::KIND_STAT, DashboardWidget::SLOT_FIGURES, 'Hadir', ['value' => 90], order: 10),
            new DashboardWidget('todo', DashboardWidget::KIND_LIST, DashboardWidget::SLOT_ATTENTION, 'Perlu tindakan', ['items' => [], 'empty' => 'Semua beres.']),
        ];
    }
}

final class ManagersOnlyDashboardWidgets implements DashboardWidgetProvider
{
    public function widgets(): array
    {
        return [
            new DashboardWidget('managers', DashboardWidget::KIND_STAT, DashboardWidget::SLOT_FIGURES, 'Akun', ['value' => 12], permission: 'identity.users.view'),
        ];
    }
}

final class BrokenDashboardWidgets implements DashboardWidgetProvider
{
    public function widgets(): array
    {
        throw new RuntimeException('provider down');
    }
}

function dashboardFor(Tenant $tenant): array
{
    return inSchool($tenant, fn () => app(DefaultDashboardRegistry::class)->widgetsForCurrentUser());
}

it('groups the widgets by slot and orders them within a slot', function () {
    $tenant = schoolAs('dash-urut');
    app(DashboardRegistry::class)->register('core', OpenDashboardWidgets::class);

    $slots = dashboardFor($tenant);

    expect(array_column($slots['figures'], 'key'))->toBe(['early', 'late'])
        ->and(array_column($slots['attention'], 'key'))->toBe(['todo']);
});

it('hides a widget from a user without its permission', function () {
    $guru = schoolAs('dash-izin-guru', 'guru');
    app(DashboardRegistry::class)->register('core', ManagersOnlyDashboardWidgets::class);

    expect(dashboardFor($guru))->toBe([]);

    $admin = schoolAs('dash-izin-admin');

    expect(array_column(dashboardFor($admin)['figures'], 'key'))->toBe(['managers']);
});

it('offers nothing from a module the school has not enabled', function () {
    $tenant = schoolAs('dash-modul');
    app(DashboardRegistry::class)->register('ppdb', OpenDashboardWidgets::class);

    expect(dashboardFor($tenant))->toBe([]);
});

it('keeps the other widgets when one provider fails', function () {
    $tenant = schoolAs('dash-gagal');
    app(DashboardRegistry::class)->register('core', BrokenDashboardWidgets::class);
    app(DashboardRegistry::class)->register('core', OpenDashboardWidgets::class);

    expect(array_column(dashboardFor($tenant)['figures'], 'key'))->toBe(['early', 'late']);
});

/**
 * A school with a student in X 1 and one Monday lesson, signed in as that
 * student.
 *
 * @return array{0: Tenant, 1: Student}
 */
function dashboardStudent(string $slug): array
{
    $tenant = schoolAs($slug, 'siswa');
    $class = classIn($tenant, ['name' => 'X 1']);
    $userId = auth()->id();

    $student = inSchool($tenant, function () use ($class, $userId): Student {
        $student = Student::factory()->create(['class_id' => $class->id]);
        $student->forceFill(['user_id' => $userId])->save();

        $slot = PeriodSlot::factory()->create(['day' => 1, 'start_time' => '07:15:00', 'end_time' => '08:00:00']);
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $teacher = Teacher::factory()->create(['name' => 'Pak Budi']);
        TeachingAssignment::factory()->create(['class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);
        TimetableEntry::factory()->create(['period_slot_id' => $slot->id, 'class_id' => $class->id, 'subject_id' => $subject->id]);

        return $student;
    });

    return [$tenant, $student];
}

afterEach(fn () => Carbon::setTestNow());

it('sends the widgets deferred, after the page shell', function () {
    $tenant = schoolAs('dash-defer');

    get(school($tenant->slug, '/beranda'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Core/Beranda')
        ->missing('widgets')
        ->loadDeferredProps(fn ($page) => $page->has('widgets')));
});

it('shows a student the lessons of today on the school clock', function () {
    Carbon::setTestNow('2026-10-05 08:00:00 Asia/Jakarta');
    [$tenant] = dashboardStudent('dash-siswa-senin');

    get(school($tenant->slug, '/beranda'))->assertOk()->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($page) => $page
            ->where('widgets.main.0.key', 'core.student-lessons-today')
            ->where('widgets.main.0.payload.items', [
                ['label' => 'Matematika', 'detail' => 'Jam ke-1 · 07:15–08:00 · Pak Budi'],
            ])));
});

it('tells a student there is no lesson on a day without one', function () {
    Carbon::setTestNow('2026-10-06 08:00:00 Asia/Jakarta');
    [$tenant] = dashboardStudent('dash-siswa-selasa');

    get(school($tenant->slug, '/beranda'))->assertOk()->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($page) => $page
            ->where('widgets.main.0.payload.items', [])
            ->where('widgets.main.0.payload.empty', 'Tidak ada pelajaran hari ini.')));
});

it('gives a teacher no student lessons', function () {
    $tenant = schoolAs('dash-guru', 'guru');

    expect(dashboardFor($tenant))->toBe([]);
});
