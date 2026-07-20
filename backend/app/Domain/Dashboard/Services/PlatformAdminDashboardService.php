<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Report\Models\Report;
use App\Models\Tenant;
use App\Models\User;

/**
 * Section 37 "Platform administrator" dashboard. Deliberately queries
 * WITHOUT tenant scoping throughout (::withoutTenantScope() /
 * BelongsToTenant bypass) -- this view exists specifically to see across
 * every tenant, which is exactly the one context where that's correct
 * rather than a bug. Access to this service must be gated by a
 * Super Administrator / Platform Administrator role check in the
 * controller, never exposed to a tenant-scoped role.
 */
class PlatformAdminDashboardService
{
    public function summary(): array
    {
        return [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'total_users' => User::withoutTenantScope()->count(),
            'active_users' => User::withoutTenantScope()->where('is_active', true)->count(),
            'total_assignments' => ValuationAssignment::withoutTenantScope()->count(),
            'assignments_last_30_days' => ValuationAssignment::withoutTenantScope()
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
            'total_reports_issued' => Report::withoutTenantScope()->where('status', 'issued')->count(),
            'platform_revenue_last_30_days' => (float) Invoice::withoutTenantScope()
                ->where('issue_date', '>=', now()->subDays(30))
                ->sum('total_amount'),
            'tenants_by_plan' => Tenant::selectRaw('plan, count(*) as count')->groupBy('plan')->pluck('count', 'plan')->all(),
        ];
    }
}
