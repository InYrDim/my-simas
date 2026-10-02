<?php

namespace Modules\Attendance\App\Domain\Queries;

use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\DTOs\ClassSubject;

/**
 * The classes offered on a recording page. Everyone may record any class
 * of the active academic year; a teacher's own classes (subjects taught,
 * homeroom) come first, so the usual choice is already made.
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

        // Stable: both halves keep the directory's order by name.
        usort($options, fn (array $a, array $b): int => $b['mine'] <=> $a['mine']);

        return $options;
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
