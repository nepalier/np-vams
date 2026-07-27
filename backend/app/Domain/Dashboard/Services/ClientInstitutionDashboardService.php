<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Valuation\Models\ValuationReconciliation;
use Illuminate\Support\Facades\DB;

/**
 * Section 37 "Client institution" dashboard, scoped to one client_id.
 *
 * Called from two places: PortalController::dashboard() (a client-portal
 * user viewing their own institution, client_id taken from the
 * authenticated user) and DashboardController::clientInstitution() (a
 * tenant staff member looking up a specific client by ID). Same query
 * logic either way -- the access-control difference lives entirely in
 * which controller/middleware fronts it, not in this service.
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
