<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Enums;

enum AssignmentPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
