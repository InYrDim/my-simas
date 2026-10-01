<?php

namespace Modules\Core\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\App\Domain\Models\Subject;

/**
 * `gradeRange` ("Kelas X–XII") comes from the school's grade levels and
 * is set by the controller with `additional()`-free wrapping: the same
 * label for every subject.
 *
 * @mixin Subject
 */
final class SubjectResource extends JsonResource
{
    public function __construct($resource, private readonly string $gradeRange = '—')
    {
        parent::__construct($resource);
    }

    /**
     * @param  iterable<Subject>  $subjects
     * @return list<array<string, mixed>>
     */
    public static function rows(iterable $subjects, string $gradeRange): array
    {
        $rows = [];

        foreach ($subjects as $subject) {
            $rows[] = (new self($subject, $gradeRange))->resolve();
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'group' => $this->group,
            'kkm' => $this->kkm,
            'grades' => $this->gradeRange,
        ];
    }
}
