<?php

namespace Modules\Core\App\Domain\Enums;

enum ImportMode: string
{
    case AddOnly = 'add';
    case Upsert = 'upsert';
}
