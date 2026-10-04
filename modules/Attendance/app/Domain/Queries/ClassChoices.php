<?php

namespace Modules\Attendance\App\Domain\Queries;

use Illuminate\Support\Facades\Gate;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\DTOs\ClassSubject;

/**
 * The classes offered on a recording page. Staff with the school-wide
 * recording permissions may record any class of the active academic year,
 * with a teacher's own classes (subjects taught, homeroom) first. A
 * teacher who only holds `attendance.class.record` gets those own classes
 * and nothing else.
 */
final class ClassChoices
{
    public function __construct(
        private readonly ClassDirectory $classes,
    ) {}

    /**
     * @return list<array{value: string, label: string, mine: bool}>
     */
    public function for(?int $userId): array
    {
        $mine = $userId === null ? [] : $this->classes->idsTaughtBy($userId);

        $options = array_map(fn ($class): array => [
            'value' => (string) $class->id,
            'label' => $class->name,
            'mine' => in_array($class->id, $mine, true),
        ], $this->classes->ofActiveYear());

        if ($this->limitedToOwnClasses()) {
            $options = array_values(array_filter($options, fn (array $option): bool => $option['mine']));
        }

        // Stable: both halves keep the directory's order by name.
        usort($options, fn (array $a, array $b): int => $b['mine'] <=> $a['mine']);

        return $options;
    }

    /**
     * Whether the user may record the class; the server-side twin of the
     * list above, so a hand-made request cannot reach another class.
     */
    public function mayRecord(int $classId, ?int $userId): bool
    {
        return ! $this->limitedToOwnClasses()
            || in_array((string) $classId, array_column($this->for($userId), 'value'), true);
    }

    private function limitedToOwnClasses(): bool
    {
        return ! Gate::any(['attendance.daily.record', 'attendance.lesson.record']);
    }

    /**
     * The class to open: the one asked for when it is offered, else the
     * first offered (a teacher's own class).
     *
     * @param  list<array{value: string, label: string, mine: bool}>  $options
     */
    public function pick(array $options, ?string $requested): ?int
    {
        $values = array_column($options, 'value');

        if ($requested !== null && in_array($requested, $values, true)) {
            return (int) $requested;
        }

        return $values === [] ? null : (int) $values[0];
    }

    /**
     * The subject the user teaches in the class, when there is exactly
     * such an assignment to suggest.
     */
    public function ownSubject(int $classId, ?int $userId): ?int
    {
        if ($userId === null) {
            return null;
        }

        $own = array_values(array_filter(
            $this->classes->subjectsOf($classId),
            fn (ClassSubject $subject): bool => $subject->teacherUserId === $userId,
        ));

        return $own === [] ? null : $own[0]->subjectId;
    }
}
