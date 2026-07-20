<?php

declare(strict_types=1);

namespace App\Domain\Review\Enums;

enum ReviewStage: string
{
    case TechnicalReview = 'technical_review';
    case FinalApproval = 'final_approval';
}
