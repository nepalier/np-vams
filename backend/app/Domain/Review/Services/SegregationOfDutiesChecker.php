<?php

declare(strict_types=1);

namespace App\Domain\Review\Services;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Models\Organization;
use App\Models\User;
use RuntimeException;

/**
 * Section 30: "The same user must not be allowed to prepare, review, and
 * approve the same report unless specifically permitted under a documented
 * exceptional policy." "Prepare" is read as the assigned valuer
 * (assigned_valuer_id) -- the person who actually did the valuation work
 * that review/approval are checking.
 */
class SegregationOfDutiesChecker
{
    public function assertCanReview(ValuationAssignment $assignment, User $user): void
    {
        $this->assertDistinctRole($assignment, $user, $assignment->assigned_valuer_id, 'prepared');
    }

    public function assertCanApprove(ValuationAssignment $assignment, User $user): void
    {
        $this->assertDistinctRole($assignment, $user, $assignment->assigned_valuer_id, 'prepared');
        $this->assertDistinctRole($assignment, $user, $assignment->assigned_reviewer_id, 'reviewed');
    }

    private function assertDistinctRole(ValuationAssignment $assignment, User $user, ?string $otherRoleUserId, string $otherRoleLabel): void
    {
        if ($otherRoleUserId === null || $otherRoleUserId !== $user->id) {
            return;
        }

        $exceptionAllowed = Organization::withoutTenantScope()
            ->whereKey($assignment->organization_id)
            ->value('allow_segregation_of_duties_exception');

        if ($exceptionAllowed) {
            return;
        }

        throw new RuntimeException(
            "You {$otherRoleLabel} this assignment and cannot also review/approve it. ".
            'This organization has not enabled the documented segregation-of-duties exception.'
        );
    }
}
