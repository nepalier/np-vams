<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Enums;

enum DepreciationMethod: string
{
    case StraightLine = 'straight_line';
    case AgeLife = 'age_life';
    case ObservedCondition = 'observed_condition';
    case ComponentWise = 'component_wise';
    case CustomProfessional = 'custom_professional';
}
