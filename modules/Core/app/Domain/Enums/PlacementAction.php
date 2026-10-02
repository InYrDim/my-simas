<?php

namespace Modules\Core\App\Domain\Enums;

enum PlacementAction: string
{
    case Promote = 'promote';
    case Move = 'move';
    case Graduate = 'graduate';
    case Assign = 'assign';
}
