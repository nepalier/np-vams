<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountStatus: string
{
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Suspended = 'suspended';
    case Blacklisted = 'blacklisted';
    case Closed = 'closed';
}
