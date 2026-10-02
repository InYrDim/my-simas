<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * How a report appears in the school's report catalogue. `key` is its
 * stable identifier (it is part of the download url), `group` the heading
 * it is listed under, and `permission` the permission a user needs to see
 * and download it (none = everyone who may open the catalogue).
 */
final readonly class ReportDefinition
{
    public function __construct(
        public string $key,
        public string $group,
        public string $name,
        public string $description,
        public ?string $permission = null,
        public int $order = 100,
    ) {}
}
