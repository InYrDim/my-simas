<?php

namespace Modules\Core\App\Domain\Queries;

use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Enums\SchoolLevel;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Extracurricular;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Major;
use Modules\Core\App\Domain\Models\Room;
use Modules\Core\App\Domain\Models\SchoolProfile;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;

/**
 * What a new school still has to fill in, in the order the data depends
 * on itself (docs/architecture/master-data-dependencies.md). Everything
 * is read from the school's own data; nothing is stored.
 *
 * A step is `done`, `next` (the first required step that can be done now,
 * only one), `todo` (required, can be done now), `blocked` (waits for
 * another step, the hint says which) or `optional`. The counts cover the
 * required steps only; once those are all done there is nothing to show
 * and the result is null.
 */
final class SetupChecklist
{
    /** How a step is named when another step waits for it. */
    private const WAITING_NAMES = [
        'profile' => 'profil sekolah',
        'year' => 'tahun ajaran aktif',
        'majors' => 'jurusan',
        'classes' => 'kelas',
    ];

    /**
     * @return array{done: int, total: int, unplaced: int, steps: list<array{key: string, label: string, hint: string|null, state: string, optional: bool}>}|null
     */
    public function forCurrentSchool(): ?array
    {
        // Not SchoolProfile::current(): reading a page must not create a row.
        $profile = SchoolProfile::query()->first();
        $level = $profile === null ? SchoolLevel::Sma : $profile->level;
        $majorsRequired = $level->hasMajors();

        $activeYear = AcademicYear::query()
            ->where('status', AcademicYearStatus::Active->value)
            ->first();
        $hasDraftYear = $activeYear === null
            && AcademicYear::query()->where('status', AcademicYearStatus::Draft->value)->exists();

        $activeClasses = $activeYear === null
            ? null
            : ClassGroup::query()->where('academic_year_id', $activeYear->id);
        $hasClasses = $activeClasses?->exists() ?? false;

        $hasPlacedStudent = $activeClasses !== null && Student::query()
            ->where('status', 'active')
            ->whereIn('class_id', $activeClasses->clone()->select('id'))
            ->exists();
        $unplaced = Student::query()->where('status', 'active')->whereNull('class_id')->count();
        $hasAnyStudent = $hasPlacedStudent || $unplaced > 0
            || Student::query()->where('status', 'active')->exists();

        /** @var list<array{key: string, label: string, done: bool, required: bool, after: list<string>, hint: string|null}> $definitions */
        $definitions = [
            [
                'key' => 'profile',
                'label' => 'Simpan profil sekolah',
                'done' => Grade::query()->exists(),
                'required' => true,
                'after' => [],
                'hint' => 'Menentukan jenjang dan mengisi daftar Tingkat.',
            ],
            [
                'key' => 'year',
                'label' => 'Buat dan aktifkan tahun ajaran',
                'done' => $activeYear !== null,
                'required' => true,
                'after' => [],
                'hint' => $hasDraftYear
                    ? 'Sudah ada tahun ajaran berstatus draf; aktifkan salah satunya.'
                    : 'Semester dibuat otomatis bersama tahun ajaran.',
            ],
            [
                'key' => 'majors',
                'label' => 'Tambah jurusan',
                'done' => Major::query()->exists(),
                'required' => $majorsRequired,
                'after' => [],
                'hint' => $majorsRequired
                    ? 'Jenjang ini memakai jurusan; isi sebelum membuat kelas.'
                    : 'Hanya bila sekolah memakai jurusan.',
            ],
            [
                'key' => 'rooms',
                'label' => 'Tambah ruangan',
                'done' => Room::query()->exists(),
                'required' => false,
                'after' => [],
                'hint' => 'Bisa dipilih saat membuat kelas.',
            ],
            [
                'key' => 'subjects',
                'label' => 'Tambah mata pelajaran',
                'done' => Subject::query()->exists(),
                'required' => true,
                'after' => [],
                'hint' => null,
            ],
            [
                'key' => 'teachers',
                'label' => 'Tambah guru dan tendik',
                'done' => Teacher::query()->exists(),
                'required' => true,
                'after' => [],
                'hint' => null,
            ],
            [
                'key' => 'classes',
                'label' => 'Buat kelas',
                'done' => $hasClasses,
                'required' => true,
                'after' => $majorsRequired ? ['profile', 'year', 'majors'] : ['profile', 'year'],
                'hint' => 'Kelas dibuat pada tahun ajaran aktif.',
            ],
            [
                'key' => 'students',
                'label' => 'Tambah dan tempatkan siswa',
                'done' => $hasPlacedStudent,
                'required' => true,
                'after' => ['classes'],
                'hint' => match (true) {
                    $unplaced > 0 => "{$unplaced} siswa belum ditempatkan di kelas.",
                    ! $hasAnyStudent => 'Tambah satu per satu atau impor dari CSV.',
                    default => null,
                },
            ],
            [
                'key' => 'extracurriculars',
                'label' => 'Tambah ekstrakurikuler',
                'done' => Extracurricular::query()->exists(),
                'required' => false,
                'after' => [],
                'hint' => null,
            ],
        ];

        $isDone = [];

        foreach ($definitions as $definition) {
            $isDone[$definition['key']] = $definition['done'];
        }

        $required = array_values(array_filter($definitions, fn (array $step): bool => $step['required']));
        $doneCount = count(array_filter($required, fn (array $step): bool => $step['done']));

        if ($doneCount === count($required)) {
            return null;
        }

        $nextTaken = false;
        $steps = [];

        foreach ($definitions as $definition) {
            $waitingFor = array_values(array_filter(
                $definition['after'],
                fn (string $key): bool => ! $isDone[$key],
            ));
            $hint = $definition['hint'];

            if ($definition['done']) {
                $state = 'done';
            } elseif (! $definition['required']) {
                $state = 'optional';
            } elseif ($waitingFor !== []) {
                $state = 'blocked';
                $hint = 'Menunggu: '.implode(', ', array_map(
                    fn (string $key): string => self::WAITING_NAMES[$key],
                    $waitingFor,
                )).'.';
            } elseif (! $nextTaken) {
                $state = 'next';
                $nextTaken = true;
            } else {
                $state = 'todo';
            }

            $steps[] = [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'hint' => $state === 'done' ? null : $hint,
                'state' => $state,
                'optional' => ! $definition['required'],
            ];
        }

        return [
            'done' => $doneCount,
            'total' => count($required),
            'unplaced' => $unplaced,
            'steps' => $steps,
        ];
    }
}
