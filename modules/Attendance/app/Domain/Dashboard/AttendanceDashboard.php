<?php

namespace Modules\Attendance\App\Domain\Dashboard;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Enums\LessonState;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Queries\AttendanceTally;
use Modules\Attendance\App\Domain\Queries\DailyRecap;
use Modules\Attendance\App\Domain\Queries\TeacherLessons;
use Modules\Attendance\App\Domain\Statistics\AttendanceStatistics;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Core\App\Contracts\DashboardWidgetProvider;
use Modules\Core\App\Contracts\DTOs\DashboardWidget;
use Modules\Core\App\Contracts\StudentDirectory;

/**
 * Attendance on the Beranda, for whoever the permissions say: the office
 * sees today's school-wide record and the classes that have not sent it,
 * a teacher sees their lessons of the day, a student sees their own day
 * and month. Every widget names the permission it needs; the registry
 * keeps only what the signed-in person may see.
 */
final class AttendanceDashboard implements DashboardWidgetProvider
{
    public function __construct(
        private readonly SchoolClock $clock,
        private readonly DailyRecap $recap,
        private readonly TeacherLessons $lessons,
        private readonly StudentDirectory $students,
        private readonly AttendanceStatistics $statistics,
    ) {}

    /**
     * @return list<DashboardWidget>
     */
    public function widgets(): array
    {
        // Each part runs its queries only for someone the permissions let
        // see it: a student never makes the school-wide recap.
        return [
            ...(Gate::any(['attendance.view', 'attendance.gate.use']) ? $this->office() : []),
            ...(Gate::allows('attendance.class.record') ? $this->teacher() : []),
            ...(Gate::any(['attendance.mine.view', 'attendance.qr.use']) ? $this->student() : []),
        ];
    }

    /**
     * Today's record of the whole school.
     *
     * @return list<DashboardWidget>
     */
    private function office(): array
    {
        $today = $this->recap->forDate($this->clock->today());
        $totals = $today['totals'];
        $recorded = $totals['total'] - $totals['pending'];
        $attended = $totals['present'] + $totals['late'];

        $waiting = array_values(array_filter($today['classes'], fn (array $class): bool => $class['total'] > 0 && ! $class['submitted']));

        $trend = $this->statistics->panels(null)[0] ?? null;

        return [
            new DashboardWidget(
                key: 'attendance.gate-action',
                kind: DashboardWidget::KIND_ACTION,
                slot: DashboardWidget::SLOT_ACTION,
                title: 'Absensi Gerbang',
                payload: ['label' => 'Absensi Gerbang'],
                order: 10,
                href: route('attendance.input', absolute: false),
                permission: 'attendance.gate.use',
            ),
            new DashboardWidget(
                key: 'attendance.rate-today',
                kind: DashboardWidget::KIND_STAT,
                slot: DashboardWidget::SLOT_FIGURES,
                title: 'Kehadiran hari ini',
                payload: [
                    'value' => $recorded === 0 ? '—' : round($attended / $recorded * 100).'%',
                    'hint' => $recorded === 0 ? 'Belum ada catatan' : "{$recorded} dari {$totals['total']} siswa tercatat",
                ],
                order: 20,
                permission: 'attendance.view',
            ),
            new DashboardWidget(
                key: 'attendance.pending-today',
                kind: DashboardWidget::KIND_STAT,
                slot: DashboardWidget::SLOT_FIGURES,
                title: 'Belum tercatat',
                payload: ['value' => $totals['pending'], 'hint' => 'siswa hari ini'],
                order: 21,
                permission: 'attendance.view',
            ),
            new DashboardWidget(
                key: 'attendance.classes-waiting',
                kind: DashboardWidget::KIND_LIST,
                slot: DashboardWidget::SLOT_ATTENTION,
                title: 'Kelas belum mengirim absensi',
                payload: [
                    'items' => array_map(fn (array $class): array => [
                        'label' => $class['name'],
                        'detail' => "{$class['pending']} siswa belum tercatat",
                        'href' => route('attendance.overview', absolute: false),
                    ], $waiting),
                    'empty' => 'Semua kelas sudah mengirim absensi.',
                ],
                order: 10,
                href: route('attendance.overview', absolute: false),
                permission: 'attendance.view',
            ),
            new DashboardWidget(
                key: 'attendance.recap-today',
                kind: DashboardWidget::KIND_LIST,
                slot: DashboardWidget::SLOT_MAIN,
                title: 'Rekap hari ini',
                payload: [
                    'items' => $this->countItems($totals),
                    'empty' => 'Belum ada catatan.',
                ],
                order: 20,
                permission: 'attendance.view',
            ),
            new DashboardWidget(
                key: 'attendance.trend',
                kind: DashboardWidget::KIND_BARS,
                slot: DashboardWidget::SLOT_MAIN,
                title: 'Kehadiran 6 bulan terakhir (%)',
                payload: ['points' => $trend?->points ?? []],
                order: 40,
                permission: 'attendance.view',
            ),
        ];
    }

    /**
     * A teacher's lessons of the day, each with where it stands.
     *
     * @return list<DashboardWidget>
     */
    private function teacher(): array
    {
        $userId = Auth::id();

        $lessons = $userId === null ? [] : $this->lessons->on((int) $userId, $this->clock->today());

        return [
            new DashboardWidget(
                key: 'attendance.class-action',
                kind: DashboardWidget::KIND_ACTION,
                slot: DashboardWidget::SLOT_ACTION,
                title: 'Absensi Kelas',
                payload: ['label' => 'Absensi Kelas'],
                order: 10,
                href: route('attendance.class-roll', absolute: false),
                permission: 'attendance.class.lesson.use',
            ),
            new DashboardWidget(
                key: 'attendance.lessons-today',
                kind: DashboardWidget::KIND_LIST,
                slot: DashboardWidget::SLOT_MAIN,
                title: 'Jadwal mengajar hari ini',
                payload: [
                    'items' => array_map(fn (array $lesson): array => [
                        'label' => "{$lesson['subjectName']} · {$lesson['className']}",
                        'detail' => "Jam ke-{$lesson['order']} · {$lesson['startsAt']}–{$lesson['endsAt']}",
                        'status' => $this->lessonWord($lesson),
                        'href' => route('attendance.schedule', absolute: false),
                    ], $lessons),
                    'empty' => 'Tidak ada jam mengajar hari ini.',
                ],
                order: 10,
                href: route('attendance.schedule', absolute: false),
                permission: 'attendance.class.record',
            ),
        ];
    }

    /**
     * @param  array{state: string, recorded: bool}  $lesson
     */
    private function lessonWord(array $lesson): string
    {
        if ($lesson['recorded']) {
            return 'Sudah diisi';
        }

        return match ($lesson['state']) {
            LessonState::Upcoming->value => 'Belum mulai',
            LessonState::Running->value => 'Berlangsung',
            default => 'Belum diisi',
        };
    }

    /**
     * A student's own day and month.
     *
     * @return list<DashboardWidget>
     */
    private function student(): array
    {
        $userId = Auth::id();
        $record = $userId === null ? null : $this->students->findByUserId((int) $userId);
        $today = $this->clock->today();

        $day = $record === null ? null : DailyAttendance::query()
            ->where('student_id', $record->id)
            ->where('date', $today)
            ->first();

        $month = new AttendanceTally;

        if ($record !== null) {
            $first = substr($today, 0, 7);

            DailyAttendance::query()
                ->where('student_id', $record->id)
                ->whereBetween('date', ["{$first}-01", "{$first}-31"])
                ->get(['status'])
                ->each(fn (DailyAttendance $row) => $month->add($row->status));
        }

        return [
            new DashboardWidget(
                key: 'attendance.qr-action',
                kind: DashboardWidget::KIND_ACTION,
                slot: DashboardWidget::SLOT_ACTION,
                title: 'QR saya',
                payload: ['label' => 'Tampilkan QR saya'],
                order: 10,
                href: route('attendance.my-qr', absolute: false),
                permission: 'attendance.qr.use',
            ),
            new DashboardWidget(
                key: 'attendance.mine-today',
                kind: DashboardWidget::KIND_STATUS,
                slot: DashboardWidget::SLOT_MAIN,
                title: 'Kehadiran saya hari ini',
                payload: $this->dayStatus($day),
                order: 10,
                href: route('attendance.mine', absolute: false),
                permission: 'attendance.mine.view',
            ),
            new DashboardWidget(
                key: 'attendance.mine-month',
                kind: DashboardWidget::KIND_LIST,
                slot: DashboardWidget::SLOT_MAIN,
                title: 'Absensi saya bulan ini',
                payload: [
                    'items' => $month->total() === 0 ? [] : $this->countItems($month->toArray()),
                    'empty' => 'Belum ada catatan bulan ini.',
                ],
                order: 20,
                permission: 'attendance.mine.view',
            ),
        ];
    }

    /**
     * The student's day as one word, always with the word spelled out.
     *
     * @return array{state: string, word: string, detail?: string}
     */
    private function dayStatus(?DailyAttendance $day): array
    {
        if ($day === null) {
            return ['state' => 'pending', 'word' => 'Belum tercatat'];
        }

        $times = array_filter([
            $day->checked_in_at === null ? null : 'Masuk '.$this->clock->time($day->checked_in_at),
            $day->checked_out_at === null ? null : 'Pulang '.$this->clock->time($day->checked_out_at),
        ]);

        return [
            'state' => match ($day->status) {
                AttendanceStatus::Present, AttendanceStatus::Late => 'confirmed',
                AttendanceStatus::Absent => 'void',
                default => 'none',
            },
            'word' => $day->status->label(),
            ...($times === [] ? [] : ['detail' => implode(' · ', $times)]),
        ];
    }

    /**
     * Status counts as list rows: the label and the count as plain text.
     *
     * @param  array<string, int>  $counts
     * @return list<array{label: string, value: string}>
     */
    private function countItems(array $counts): array
    {
        $items = [];

        foreach (AttendanceStatus::cases() as $status) {
            $items[] = ['label' => $status->label(), 'value' => (string) ($counts[$status->value] ?? 0)];
        }

        return $items;
    }
}
