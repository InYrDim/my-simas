<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * One notice about one student, to be sent to that student's guardian.
 */
final readonly class GuardianNotice
{
    /**
     * @param  int  $studentId  Core's student id, in the current school
     * @param  string  $kind  key of a registered NoticeKind
     * @param  array<string, string>  $variables  values for the kind's own variables
     */
    public function __construct(
        public int $studentId,
        public string $kind,
        public array $variables = [],
    ) {}
}
