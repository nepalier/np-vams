<?php

declare(strict_types=1);

namespace App\Domain\ClientPortal\Http\Controllers;

use App\Domain\Assignment\Http\Resources\AssignmentResource;
use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Billing\Http\Resources\InvoiceResource;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Dashboard\Services\ClientInstitutionDashboardService;
use App\Domain\Report\Http\Resources\ReportResource;
use App\Domain\Report\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything here relies on ClientPortalScope having already narrowed
 * ValuationAssignment/Invoice/Report to the authenticated user's own
 * client_id (bound by IdentifyTenant middleware) -- these controllers
 * don't add their own ->where('client_id', ...) filters, deliberately,
 * to prove the scope itself is what's doing the work rather than
 * duplicated per-controller logic that could drift out of sync with it.
 */
class PortalController
{
    public function dashboard(Request $request, ClientInstitutionDashboardService $service): JsonResponse
    {
        return response()->json(['data' => $service->summary($request->user()->client_id)]);
    }

    public function assignments(Request $request): JsonResponse
    {
        $assignments = ValuationAssignment::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return AssignmentResource::collection($assignments)->response();
    }

    public function assignment(Request $request, string $assignmentId): JsonResponse
    {
        $assignment = ValuationAssignment::findOrFail($assignmentId);

        return (new AssignmentResource($assignment->load('properties')))->response();
    }

    public function invoices(Request $request): JsonResponse
    {
        $invoices = Invoice::query()
            ->orderByDesc('issue_date')
            ->paginate($request->integer('per_page', 20));

        return InvoiceResource::collection($invoices)->response();
    }

    public function reports(Request $request): JsonResponse
    {
        $reports = Report::query()
            ->where('status', 'issued')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return ReportResource::collection($reports)->response();
    }
}
