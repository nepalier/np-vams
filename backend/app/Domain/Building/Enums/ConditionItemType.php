<?php

declare(strict_types=1);

namespace App\Domain\Building\Enums;

enum ConditionItemType: string
{
    case Foundation = 'foundation';
    case Columns = 'columns';
    case Beams = 'beams';
    case Slabs = 'slabs';
    case Walls = 'walls';
    case Cracks = 'cracks';
    case Settlement = 'settlement';
    case Dampness = 'dampness';
    case Roof = 'roof';
    case Doors = 'doors';
    case Windows = 'windows';
    case Electrical = 'electrical';
    case Plumbing = 'plumbing';
    case Sanitation = 'sanitation';
    case FireSafety = 'fire_safety';
    case Lift = 'lift';
    case Hvac = 'hvac';
    case Maintenance = 'maintenance';
    case FunctionalObsolescence = 'functional_obsolescence';
    case EconomicObsolescence = 'economic_obsolescence';
}
