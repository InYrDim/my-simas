<?php

namespace Modules\Core\App\Domain\Enums;

enum AcademicYearStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
