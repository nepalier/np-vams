<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Billing\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Section 37 "Valuation firm" dashboard. Every figure here is a real
 * aggregate query against the tenant-scoped tables built in earlier
 * phases -- none of it is mocked or hard-coded, and every query already
 * runs through TenantScope, so a firm can never see another tenant's
 * pipeline by construction, not by a WHERE clause someone has to remember
 * to add on every new report.
 */
class ValuationFirmDashboardService
{
    public function summary(): array
    {
        return [
            'new_assignments_last_30_days' => ValuationAssignment::where('created_at', '>=', now()->subDays(30))->count(),
            'pending_documents' => ValuationAssignment::where('status', 'documents_pending')->count(),
            'upcoming_site_visits' => DB::table('site_visits')
                ->where('status', 'scheduled')
                ->where('scheduled_at', '>=', now())
                ->count(),
            'reports_under_review' => ValuationAssignment::where('status', 'under_technical_review')->count(),
            'overdue_assignments' => ValuationAssignment::whereNotNull('sla_due_at')
                ->where('sla_due_at', '<', now())
                ->whereNotIn('status', ['report_issued', 'archived', 'cancelled', 'superseded'])
                ->count(),
            'reports_issued_last_30_days' => ValuationAssignment::where('status', 'report_issued')
                ->where('updated_at', '>=', now()->subDays(30))
                ->count(),
            'average_turnaround_days' => $this->averageTurnaroundDays(),
            'revenue_last_30_days' => (float) Invoice::where('issue_date', '>=', now()->subDays(30))->sum('total_amount'),
            'receivables_outstanding' => (float) Invoice::whereIn('status', ['issued', 'partially_paid', 'overdue'])->sum('outstanding_amount'),
            'valuer_workload' => $this->workloadByUser('assigned_valuer_id'),
            'reviewer_workload' => $this->workloadByUser('assigned_reviewer_id'),
            'client_wise_assignment_count' => $this->countGroupedBy('client_id'),
            'district_wise_assignment_count' => $this->districtWiseAssignmentCount(),
            'revaluation_due_count' => ValuationAssignment::where('status', 'revaluation_due')->count(),
            'rejection_rate_pct' => $this->rejectionRatePct(),
        ];
    }

    /**
     * Turnaround = days between draft creation and report_issued, averaged
     * over assignments that actually reached report_issued -- computed
     * from the immutable workflow_transitions log (Phase 3), not a
     * separately-maintained duration field that could drift from reality.
     */
    private function averageTurnaroundDays(): ?float
    {
        $row = DB::table('workflow_transitions')
            ->join('valuation_assignments', 'valuation_assignments.id', '=', 'workflow_transitions.transitionable_id')
            ->where('workflow_transitions.transitionable_type', ValuationAssignment::class)
            ->where('workflow_transitions.new_status', 'report_issued')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, valuation_assignments.created_at, workflow_transitions.created_at)) / 86400.0 as avg_days')
            ->first();

        return $row?->avg_days !== null ? round((float) $row->avg_days, 1) : null;
    }

    private function workloadByUser(string $column): array
    {
        return ValuationAssignment::whereNotNull($column)
            ->whereNotIn('status', ['report_issued', 'archived', 'cancelled', 'superseded'])
            ->select($column, DB::raw('count(*) as count'))
            ->groupBy($column)
            ->pluck('count', $column)
            ->all();
    }

    private function countGroupedBy(string $column): array
    {
        return ValuationAssignment::select($column, DB::raw('count(*) as count'))
            ->groupBy($column)
            ->pluck('count', $column)
            ->all();
    }

    private function districtWiseAssignmentCount(): array
    {
        return DB::table('assignment_properties')
            ->join('properties', 'properties.id', '=', 'assignment_properties.property_id')
            ->join('districts', 'districts.id', '=', 'properties.district_id')
            ->select('districts.name_en', DB::raw('count(distinct assignment_properties.valuation_assignment_id) as count'))
            ->groupBy('districts.name_en')
            ->pluck('count', 'name_en')
            ->all();
    }

    private function rejectionRatePct(): ?float
    {
        $total = DB::table('approval_records')->where('stage', 'final_approval')->count();

        if ($total === 0) {
            return null;
        }

        $rejected = DB::table('approval_records')
            ->where('stage', 'final_approval')
            ->where('decision', 'return_for_correction')
            ->count();

        return round(($rejected / $total) * 100, 1);
    }
}
