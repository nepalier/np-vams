<?php

declare(strict_types=1);

namespace App\Domain\Review\Services;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Review\Enums\ReviewStage;
use App\Domain\Review\Models\ApprovalRecord;
use App\Domain\Workflow\Services\WorkflowEngine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function __construct(
        private readonly SegregationOfDutiesChecker $segregationChecker,
        private readonly WorkflowEngine $workflowEngine,
    ) {}

    /**
     * @param  'approve'|'return_for_correction'|'cancel'  $decision
     */
    public function decide(ValuationAssignment $assignment, User $user, string $decision, ?string $remarks): ApprovalRecord
    {
        $this->segregationChecker->assertCanApprove($assignment, $user);

        return DB::transaction(function () use ($assignment, $user, $decision, $remarks) {
            $record = ApprovalRecord::create([
                'tenant_id' => $assignment->tenant_id,
                'valuation_assignment_id' => $assignment->id,
                'stage' => ReviewStage::FinalApproval->value,
                'decision' => $decision,
                'decided_by_user_id' => $user->id,
                'remarks' => $remarks,
                'decided_at' => now(),
            ]);

            $toStatus = match ($decision) {
                'approve' => 'approved',
                'return_for_correction' => 'correction_requested',
                'cancel' => 'cancelled',
                default => null,
            };

            if ($toStatus !== null) {
                $this->workflowEngine->transition($assignment, $toStatus, $user, $remarks);
            }

            return $record;
        });
    }
}
