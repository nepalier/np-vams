<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\Models\WorkflowStatus;
use App\Domain\Workflow\Models\WorkflowTransition;
use App\Domain\Workflow\Models\WorkflowTransitionRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Configurable workflow engine (Step 1 Section 7).
 *
 * The graph lives entirely in workflow_statuses / workflow_transition_rules
 * (DB-driven master data), not in a hard-coded switch statement -- an
 * org admin can add/relabel a status or open a new edge without a
 * deployment. What this class guarantees, regardless of configuration:
 *
 *  1. A transition not present in workflow_transition_rules is rejected.
 *  2. A transition whose rule names allowed_roles is rejected unless the
 *     acting user holds one of those roles.
 *  3. A transition into a status that requires remarks is rejected without
 *     remarks (e.g. "Correction Requested" without saying what to correct).
 *  4. Every successful transition writes an immutable workflow_transitions
 *     row AND updates the model's `status` column inside one DB transaction,
 *     so the two can never drift out of sync.
 */
class WorkflowEngine
{
    /**
     * @throws RuntimeException on any guard failure
     */
    public function transition(
        Model $subject,
        string $toStatusCode,
        User $user,
        ?string $remarks = null,
        ?Request $request = null,
    ): WorkflowTransition {
        $fromStatusCode = $subject->status;

        $fromStatus = WorkflowStatus::where('code', $fromStatusCode)->firstOrFail();
        $toStatus = WorkflowStatus::where('code', $toStatusCode)->firstOrFail();

        $rule = WorkflowTransitionRule::where('from_status_id', $fromStatus->id)
            ->where('to_status_id', $toStatus->id)
            ->first();

        if ($rule === null) {
            throw new RuntimeException(
                "Transition from '{$fromStatusCode}' to '{$toStatusCode}' is not permitted by the configured workflow."
            );
        }

        if (! empty($rule->allowed_roles) && ! $user->hasAnyRole($rule->allowed_roles)) {
            throw new RuntimeException(
                "You do not hold a role permitted to move this record from '{$fromStatusCode}' to '{$toStatusCode}'."
            );
        }

        if ($rule->requires_remarks && empty($remarks)) {
            throw new RuntimeException("Remarks are required to transition to '{$toStatusCode}'.");
        }

        return DB::transaction(function () use ($subject, $fromStatusCode, $toStatusCode, $user, $remarks, $request) {
            $subject->forceFill(['status' => $toStatusCode])->save();

            return WorkflowTransition::create([
                'tenant_id' => $subject->tenant_id,
                'organization_id' => $subject->organization_id ?? null,
                'organization_branch_id' => $subject->organization_branch_id ?? null,
                'transitionable_type' => $subject::class,
                'transitionable_id' => $subject->id,
                'previous_status' => $fromStatusCode,
                'new_status' => $toStatusCode,
                'user_id' => $user->id,
                'remarks' => $remarks,
                'attachments' => null,
                'ip_address' => $request?->ip(),
                'device_info' => $request?->userAgent(),
            ]);
        });
    }

    /** Statuses reachable in one step from the subject's current status. */
    public function availableTransitions(Model $subject): array
    {
        $fromStatus = WorkflowStatus::where('code', $subject->status)->first();

        if ($fromStatus === null) {
            return [];
        }

        return WorkflowTransitionRule::where('from_status_id', $fromStatus->id)
            ->with('toStatus')
            ->get()
            ->pluck('toStatus.code')
            ->all();
    }
}
