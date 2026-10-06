<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Core\App\Domain\Models\TimetableEntry;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/**
 * Jadwal Pelajaran (admin) and Jadwal Mengajar (teacher): a lesson is a
 * subject in a lesson slot of a class; the teacher comes from the class
 * assignment.
 *
 * @return array{0: Tenant, 1: ClassGroup, 2: PeriodSlot, 3: Subject, 4: Teacher}
 */
function timetableSetup(string $slug, string $role = 'admin-sekolah'): array
{
    $tenant = schoolAs($slug, $role);
    $class = classIn($tenant, ['name' => 'X 1']);
    $slot = inSchool($tenant, fn () => PeriodSlot::factory()->create(['day' => 1, 'start_time' => '07:15:00', 'end_time' => '08:00:00']));
    $subject = inSchool($tenant, fn () => Subject::factory()->create(['name' => 'Matematika']));
    $teacher = inSchool($tenant, fn () => Teacher::factory()->create(['name' => 'Pak Budi']));
    inSchool($tenant, fn () => TeachingAssignment::factory()->create(['class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]));

    return [$tenant, $class, $slot, $subject, $teacher];
}

it('lists a class lesson slots with the subject and its teacher', function () {
    [$tenant, $class, $slot, $subject] = timetableSetup('tt-list');
    inSchool($tenant, fn () => TimetableEntry::factory()->create(['period_slot_id' => $slot->id, 'class_id' => $class->id, 'subject_id' => $subject->id]));

    get(school($tenant->slug, '/akademik/jadwal'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Core/Academic/Timetable/Index')
        ->where('classId', $class->id)
        ->where('subjects.0.label', 'Matematika · Pak Budi')
        ->where('days.6.day', 'Minggu')
        ->where('days.0.slots.0', ['id' => $slot->id, 'order' => 1, 'start' => '07:15', 'end' => '08:00', 'subjectId' => $subject->id, 'subject' => 'Matematika', 'teacher' => 'Pak Budi']));
});

it('puts a subject in a slot and empties it again', function () {
    [$tenant, $class, $slot, $subject] = timetableSetup('tt-save');

    put(school($tenant->slug, '/akademik/jadwal'), ['period_slot_id' => $slot->id, 'class_id' => $class->id, 'subject_id' => $subject->id])->assertRedirect();

    expect(inSchool($tenant, fn () => TimetableEntry::query()->where('class_id', $class->id)->value('subject_id')))->toBe($subject->id);

    put(school($tenant->slug, '/akademik/jadwal'), ['period_slot_id' => $slot->id, 'class_id' => $class->id, 'subject_id' => 'none'])->assertRedirect();

    expect(inSchool($tenant, fn () => TimetableEntry::query()->count()))->toBe(0);
});

it('refuses a subject the class has no teacher for', function () {
    [$tenant, $class, $slot] = timetableSetup('tt-noteacher');
    $other = inSchool($tenant, fn () => Subject::factory()->create(['name' => 'Seni']));

    put(school($tenant->slug, '/akademik/jadwal'), ['period_slot_id' => $slot->id, 'class_id' => $class->id, 'subject_id' => $other->id])
        ->assertSessionHasErrors('subject_id');

    expect(inSchool($tenant, fn () => TimetableEntry::query()->count()))->toBe(0);
});

it('refuses to put one teacher in two classes at the same time', function () {
    [$tenant, $class, $slot, $subject, $teacher] = timetableSetup('tt-double');
    $second = classIn($tenant, ['name' => 'X 2']);
    inSchool($tenant, fn () => TeachingAssignment::factory()->create(['class_id' => $second->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]));

    put(school($tenant->slug, '/akademik/jadwal'), ['period_slot_id' => $slot->id, 'class_id' => $class->id, 'subject_id' => $subject->id])->assertRedirect();

    put(school($tenant->slug, '/akademik/jadwal'), ['period_slot_id' => $slot->id, 'class_id' => $second->id, 'subject_id' => $subject->id])
        ->assertSessionHasErrors(['subject_id' => 'Guru ini sudah mengajar di kelas X 1 pada jam yang sama.']);
});

it('refuses a slot that is not a lesson or does not exist', function () {
    [$tenant, $class, $slot, $subject] = timetableSetup('tt-slot');
    $break = inSchool($tenant, fn () => PeriodSlot::factory()->create(['day' => 1, 'start_time' => '10:00:00', 'end_time' => '10:15:00', 'type' => 'Istirahat']));

    put(school($tenant->slug, '/akademik/jadwal'), ['period_slot_id' => $break->id, 'class_id' => $class->id, 'subject_id' => $subject->id])
        ->assertSessionHasErrors('period_slot_id');

    put(school($tenant->slug, '/akademik/jadwal'), ['period_slot_id' => 999999, 'class_id' => $class->id, 'subject_id' => $subject->id])
        ->assertSessionHasErrors('period_slot_id');
});

it('lets office staff read the timetable but not change it', function () {
    [$tenant, $class, $slot, $subject] = timetableSetup('tt-guru', 'staf-tu');

    get(school($tenant->slug, '/akademik/jadwal'))->assertOk();

    put(school($tenant->slug, '/akademik/jadwal'), ['period_slot_id' => $slot->id, 'class_id' => $class->id, 'subject_id' => $subject->id])->assertForbidden();
});

it('drops lessons when their slot or their teacher assignment goes away', function () {
    [$tenant, $class, $slot, $subject] = timetableSetup('tt-cleanup');
    $secondSlot = inSchool($tenant, fn () => PeriodSlot::factory()->create(['day' => 1, 'start_time' => '08:00:00', 'end_time' => '08:45:00']));

    foreach ([$slot, $secondSlot] as $each) {
        inSchool($tenant, fn () => TimetableEntry::factory()->create(['period_slot_id' => $each->id, 'class_id' => $class->id, 'subject_id' => $subject->id]));
    }

    delete(school($tenant->slug, "/akademik/jam-pelajaran/{$slot->id}"))->assertRedirect();

    expect(inSchool($tenant, fn () => TimetableEntry::query()->pluck('period_slot_id')->all()))->toBe([$secondSlot->id]);

    put(school($tenant->slug, "/akademik/pengampu/{$class->id}"), ['assignments' => []])->assertRedirect();

    expect(inSchool($tenant, fn () => TimetableEntry::query()->count()))->toBe(0);
});

it('shows a teacher only their own lessons', function () {
    [$tenant, $class, $slot, $subject, $teacher] = timetableSetup('tt-mine', 'guru');
    inSchool($tenant, fn () => $teacher->forceFill(['user_id' => auth()->id()])->save());

    $otherSubject = inSchool($tenant, fn () => Subject::factory()->create(['name' => 'Seni']));
    $otherTeacher = inSchool($tenant, fn () => Teacher::factory()->create(['name' => 'Bu Sari']));
    $secondSlot = inSchool($tenant, fn () => PeriodSlot::factory()->create(['day' => 1, 'start_time' => '08:00:00', 'end_time' => '08:45:00']));
    inSchool($tenant, fn () => TeachingAssignment::factory()->create(['class_id' => $class->id, 'subject_id' => $otherSubject->id, 'teacher_id' => $otherTeacher->id]));

    inSchool($tenant, function () use ($slot, $secondSlot, $class, $subject, $otherSubject): void {
        TimetableEntry::factory()->create(['period_slot_id' => $slot->id, 'class_id' => $class->id, 'subject_id' => $subject->id]);
        TimetableEntry::factory()->create(['period_slot_id' => $secondSlot->id, 'class_id' => $class->id, 'subject_id' => $otherSubject->id]);
    });

    get(school($tenant->slug, '/saya/jadwal'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Core/Me/Timetable')
        ->has('days', 1)
        ->where('days.0.day', 'Senin')
        ->has('days.0.lessons', 1)
        ->where('days.0.lessons.0', ['order' => 1, 'start' => '07:15', 'end' => '08:00', 'class' => 'X 1', 'subject' => 'Matematika']));
});

it('keeps the teaching timetable from roles without core.teaching.view', function (string $role) {
    $tenant = schoolAs("tt-me-{$role}", $role);

    get(school($tenant->slug, '/saya/jadwal'))->assertForbidden();
})->with(['staf-tu', 'siswa', 'admin-sekolah']);
