<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Http\Controllers;

use App\Domain\Dashboard\Services\ClientInstitutionDashboardService;
use App\Domain\Dashboard\Services\MarketAnalyticsDashboardService;
use App\Domain\Dashboard\Services\PlatformAdminDashboardService;
use App\Domain\Dashboard\Services\ValuationFirmDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController
{
    public function firm(Request $request, ValuationFirmDashboardService $service): JsonResponse
    {
        $request->user()->can('dashboards.view') || abort(403);

        return response()->json(['data' => $service->summary()]);
    }

    public function marketAnalytics(Request $request, MarketAnalyticsDashboardService $service): JsonResponse
    {
        $request->user()->can('dashboards.view') || abort(403);

        return response()->json(['data' => $service->summary()]);
    }

    public function platform(Request $request, PlatformAdminDashboardService $service): JsonResponse
    {
        // Cross-tenant view -- gated by role, not the tenant-scoped
        // 'dashboards.view' permission, since every tenant's staff holds
        // that permission for their OWN dashboard.
        $request->user()->hasAnyRole(['Super Administrator', 'Platform Administrator']) || abort(403);

        return response()->json(['data' => $service->summary()]);
    }

    public function clientInstitution(Request $request, string $clientId, ClientInstitutionDashboardService $service): JsonResponse
    {
        $request->user()->can('dashboards.view') || abort(403);

        return response()->json(['data' => $service->summary($clientId)]);
    }
}
