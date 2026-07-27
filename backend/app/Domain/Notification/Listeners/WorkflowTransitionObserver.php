<?php

declare(strict_types=1);

namespace App\Domain\Notification\Listeners;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Notification\Notifications\AssignmentWorkflowNotification;
use App\Domain\Notification\Notifications\CorrectionRequestedNotification;
use App\Domain\Notification\Notifications\ReportIssuedNotification;
use App\Domain\Workflow\Models\WorkflowTransition;
use App\Models\User;

/**
 * Reference wiring for 6 of the ~18 lifecycle events in Section 36
 * (report_issued, correction_requested, valuer_assigned, awaiting_approval,
 * approved, revaluation_due). The remaining events follow this identical
 * "on WorkflowTransition created, look at new_status, notify the relevant
 * assignee" shape.
 */
class WorkflowTransitionObserver
{
    public function created(WorkflowTransition $transition): void
    {
        if ($transition->transitionable_type !== ValuationAssignment::class) {
            return;
        }

        $assignment = $transition->transitionable;

        if ($assignment === null) {
            return;
        }

        match ($transition->new_status) {
            'report_issued' => $this->notify($assignment->assigned_valuer_id, new ReportIssuedNotification($assignment)),
            'correction_requested' => $this->notify($assignment->assigned_valuer_id, new CorrectionRequestedNotification($assignment, $transition->remarks)),
            'valuer_assigned' => $this->notify($assignment->assigned_valuer_id, new AssignmentWorkflowNotification($assignment, 'valuer_assigned')),
            'awaiting_approval' => $this->notify($assignment->assigned_approver_id, new AssignmentWorkflowNotification($assignment, 'awaiting_approval')),
            'approved' => $this->notify($assignment->assigned_valuer_id, new AssignmentWorkflowNotification($assignment, 'approved')),
            'revaluation_due' => $this->notify($assignment->assigned_valuer_id, new AssignmentWorkflowNotification($assignment, 'revaluation_due')),
            default => null,
        };
    }

    private function notify(?string $userId, $notification): void
    {
        if ($userId === null) {
            return;
        }

        $user = User::withoutTenantScope()->find($userId);
        $user?->notify($notification);
    }
}
