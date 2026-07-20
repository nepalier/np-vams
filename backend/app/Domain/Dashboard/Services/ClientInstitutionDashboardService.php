<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Valuation\Models\ValuationReconciliation;
use Illuminate\Support\Facades\DB;

/**
 * Section 37 "Client institution" dashboard, scoped to one client_id
 * within the CURRENT tenant.
 *
 * KNOWN GAP, documented rather than silently worked around: Section 3
 * lists "Client Institution Administrator" / "Bank Branch User" as
 * portal-login roles, implying the bank's own staff log in and see only
 * their institution's cases. This schema's `clients`/`client_branches`
 * (Phase 3) are NOT User-linked -- they're the valuation firm's records
 * of its counterparties, and most clients never authenticate at all
 * (Phase 3 README). Giving a bank's own staff a real login that's scoped
 * to `client_id` instead of `organization_id` needs a second tenancy
 * axis on the User model (or a separate client-portal guard) that hasn't
 * been built. This service is written so a valuation firm's own staff can
 * pull up "how is Client X doing" today, and is the exact query shape a
 * future client-portal endpoint would reuse once that auth model exists.
 */
class ClientInstitutionDashboardService
{
    public function summary(string $clientId): array
    {
        $baseQuery = fn () => ValuationAssignment::where('client_id', $clientId);

        return [
            'total_requests' => $baseQuery()->count(),
            'pending_cases' => $baseQuery()->whereNotIn('status', [
                'report_issued', 'archived', 'cancelled', 'superseded',
            ])->count(),
            'reports_received' => $baseQuery()->where('status', 'report_issued')->count(),
            'average_turnaround_days' => $this->averageTurnaroundDays($clientId),
            'branch_wise_case_count' => $baseQuery()
                ->select('client_branch_id', DB::raw('count(*) as count'))
                ->groupBy('client_branch_id')
                ->pluck('count', 'client_branch_id')
                ->all(),
            'revaluation_due_count' => $baseQuery()->where('status', 'revaluation_due')->count(),
            'property_value_distribution' => $this->propertyValueDistribution($clientId),
        ];
    }

    private function averageTurnaroundDays(string $clientId): ?float
    {
        $row = DB::table('workflow_transitions')
            ->join('valuation_assignments', 'valuation_assignments.id', '=', 'workflow_transitions.transitionable_id')
            ->where('valuation_assignments.client_id', $clientId)
            ->where('workflow_transitions.transitionable_type', ValuationAssignment::class)
            ->where('workflow_transitions.new_status', 'report_issued')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, valuation_assignments.created_at, workflow_transitions.created_at)) / 86400.0 as avg_days')
            ->first();

        return $row?->avg_days !== null ? round((float) $row->avg_days, 1) : null;
    }

    /** Buckets reconciled market values into broad bands for this client's completed valuations. */
    private function propertyValueDistribution(string $clientId): array
    {
        $values = ValuationReconciliation::whereHas(
            'valuationAssignment',
            fn ($q) => $q->where('client_id', $clientId)
        )->pluck('rounded_market_value');

        $bands = ['<1M' => 0, '1M-5M' => 0, '5M-10M' => 0, '10M-50M' => 0, '50M+' => 0];

        foreach ($values as $value) {
            $value = (float) $value;
            $bands[match (true) {
                $value < 1_000_000 => '<1M',
                $value < 5_000_000 => '1M-5M',
                $value < 10_000_000 => '5M-10M',
                $value < 50_000_000 => '10M-50M',
                default => '50M+',
            }]++;
        }

        return $bands;
    }
}
