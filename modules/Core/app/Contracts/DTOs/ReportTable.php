<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * The content of a report: one table. Core turns it into the CSV download
 * and the print view, so a report never deals with file formats.
 */
final readonly class ReportTable
{
    /**
     * @param  list<string>  $columns
     * @param  list<list<string|int|float|null>>  $rows  each row in the order of the columns
     */
    public function __construct(
        public string $title,
        public array $columns,
        public array $rows,
    ) {}
}
