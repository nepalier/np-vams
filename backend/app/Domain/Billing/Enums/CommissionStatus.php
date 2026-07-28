<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum CommissionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
