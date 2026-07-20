<?php

declare(strict_types=1);

namespace App\Domain\Document\Enums;

enum DocumentVerificationStatus: string
{
    case Received = 'received';
    case OriginalSeen = 'original_seen';
    case CopyReceived = 'copy_received';
    case OnlineVerified = 'online_verified';
    case AuthorityVerified = 'authority_verified';
    case Expired = 'expired';
    case Incomplete = 'incomplete';
    case NotApplicable = 'not_applicable';
    case SuspectedInconsistency = 'suspected_inconsistency';
    case ClarificationRequired = 'clarification_required';
    case Rejected = 'rejected';
}
