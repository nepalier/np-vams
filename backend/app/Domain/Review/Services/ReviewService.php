<?php

declare(strict_types=1);

namespace App\Domain\Review\Services;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Review\Enums\ReviewStage;
use App\Domain\Review\Models\ApprovalRecord;
use App\Domain\Review\Models\ReviewComment;
use App\Domain\Workflow\Services\WorkflowEngine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function __construct(
        private readonly SegregationOfDutiesChecker $segregationChecker,
        private readonly WorkflowEngine $workflowEngine,
    ) {}

    public function addComment(ValuationAssignment $assignment, User $user, array $data): ReviewComment
    {
        return ReviewComment::create([
            'tenant_id' => $assignment->tenant_id,
            'valuation_assignment_id' => $assignment->id,
            'section' => $data['section'] ?? null,
            'comment_type' => $data['comment_type'] ?? 'inline',
            'comment' => $data['comment'],
            'severity' => $data['severity'] ?? 'information',
            'created_by_user_id' => $user->id,
        ]);
    }

    /**
     * @param  'accept'|'reject'|'recommend_approval'  $decision
     */
    public function recordTechnicalReviewDecision(ValuationAssignment $assignment, User $user, string $decision, ?string $remarks): ApprovalRecord
    {
        $this->segregationChecker->assertCanReview($assignment, $user);

        return DB::transaction(function () use ($assignment, $user, $decision, $remarks) {
            $record = ApprovalRecord::create([
                'tenant_id' => $assignment->tenant_id,
                'valuation_assignment_id' => $assignment->id,
                'stage' => ReviewStage::TechnicalReview->value,
                'decision' => $decision,
                'decided_by_user_id' => $user->id,
                'remarks' => $remarks,
                'decided_at' => now(),
            ]);

            $toStatus = match ($decision) {
                'recommend_approval' => 'awaiting_approval',
                'reject' => 'correction_requested',
                default => null,
            };

            if ($toStatus !== null) {
                $this->workflowEngine->transition($assignment, $toStatus, $user, $remarks);
            }

            return $record;
        });
    }
}
