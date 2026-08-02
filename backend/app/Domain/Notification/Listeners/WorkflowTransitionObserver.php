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
 * Reference wiring for 10 of the ~18 lifecycle events in Section 36
 * (report_issued, correction_requested, valuer_assigned, awaiting_approval,
 * approved, revaluation_due, site_visit_scheduled, inspection_completed,
 * cancelled, superseded). The remaining events (invoice_issued,
 * payment_overdue, registration_expiring, document_missing, assignment_
 * rejected, clarification_required) need a different trigger mechanism
 * than this observer -- most are tied to the Invoice/Payment models or a
 * scheduled command (checking license expiry, overdue invoices), not a
 * ValuationAssignment workflow transition -- flagged here rather than
 * forced into this class's shape where they don't actually fit.
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
            'site_visit_scheduled' => $this->notify(
                $assignment->assigned_surveyor_id ?? $assignment->assigned_valuer_id,
                new AssignmentWorkflowNotification($assignment, 'site_visit_scheduled'),
            ),
            'inspection_completed' => $this->notify(
                $assignment->assigned_reviewer_id ?? $assignment->assigned_valuer_id,
                new AssignmentWorkflowNotification($assignment, 'inspection_completed'),
            ),
            'cancelled' => $this->notify(
                $assignment->assigned_valuer_id,
                new AssignmentWorkflowNotification($assignment, 'cancelled', ['remarks' => $transition->remarks ?? '']),
            ),
            'superseded' => $this->notify(
                $assignment->assigned_valuer_id,
                new AssignmentWorkflowNotification($assignment, 'superseded', ['remarks' => $transition->remarks ?? '']),
            ),
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
