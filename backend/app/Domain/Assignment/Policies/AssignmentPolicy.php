<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Policies;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Models\User;

class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('assignments.view');
    }

    public function view(User $user, ValuationAssignment $assignment): bool
    {
        if ($assignment->tenant_id !== $user->tenant_id) {
            return false;
        }

        if ($user->isClientPortalUser()) {
            return $assignment->client_id === $user->client_id;
        }

        // Ownership-scoped viewing: assignees can always see their own
        // assignments even without the blanket assignments.view permission
        // (e.g. a Field Surveyor sees only what they're assigned to).
        $isAssignee = in_array($user->id, [
            $assignment->assigned_valuer_id,
            $assignment->assigned_surveyor_id,
            $assignment->assigned_reviewer_id,
            $assignment->assigned_approver_id,
        ], true);

        return $user->can('assignments.view') || $isAssignee;
    }

    public function create(User $user): bool
    {
        return $user->can('assignments.create');
    }

    public function update(User $user, ValuationAssignment $assignment): bool
    {
        return $assignment->tenant_id === $user->tenant_id && $user->can('assignments.update');
    }

    public function transition(User $user, ValuationAssignment $assignment): bool
    {
        // The actual allowed-roles-per-edge check happens inside
        // WorkflowEngine (data-driven); this policy method only confirms
        // tenant ownership before we even attempt a transition.
        return $assignment->tenant_id === $user->tenant_id;
    }
}
