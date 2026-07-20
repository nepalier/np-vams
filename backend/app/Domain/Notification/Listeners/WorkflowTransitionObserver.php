<?php

declare(strict_types=1);

namespace App\Domain\Notification\Listeners;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Notification\Notifications\CorrectionRequestedNotification;
use App\Domain\Notification\Notifications\ReportIssuedNotification;
use App\Domain\Workflow\Models\WorkflowTransition;
use App\Models\User;

/**
 * Reference wiring for two of the ~18 lifecycle events in Section 36
 * (report_issued, correction_requested). The other events follow this
 * identical "on WorkflowTransition created, look at new_status, notify the
 * relevant assignee" shape and are the next pieces to add once the
 * bilingual template layer referenced in the Notification classes exists.
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
            'report_issued' => $this->notifyValuer($assignment, new ReportIssuedNotification($assignment)),
            'correction_requested' => $this->notifyValuer($assignment, new CorrectionRequestedNotification($assignment, $transition->remarks)),
            default => null,
        };
    }

    private function notifyValuer(ValuationAssignment $assignment, $notification): void
    {
        if ($assignment->assigned_valuer_id === null) {
            return;
        }

        $valuer = User::withoutTenantScope()->find($assignment->assigned_valuer_id);
        $valuer?->notify($notification);
    }
}
