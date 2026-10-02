<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * One panel of the Statistik page: a labelled set of numbers and the way
 * to draw it. `bars` compares the points with each other (one bar each);
 * `share` shows them as parts of one whole.
 */
final readonly class StatPanel
{
    public const KIND_BARS = 'bars';

    public const KIND_SHARE = 'share';

    /**
     * @param  self::KIND_*  $kind
     * @param  list<array{label: string, value: int|float}>  $points
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $kind,
        public array $points,
        public ?string $note = null,
    ) {}
}
