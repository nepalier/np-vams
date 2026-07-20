<?php

declare(strict_types=1);

namespace App\Domain\Review\Enums;

enum ReviewDecision: string
{
    case Accept = 'accept';
    case Reject = 'reject';
    case RecommendApproval = 'recommend_approval';
    case Approve = 'approve';
    case ReturnForCorrection = 'return_for_correction';
    case Cancel = 'cancel';
    case Supersede = 'supersede';
}
