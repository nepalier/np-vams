<?php

declare(strict_types=1);

namespace App\Domain\SiteVisit\Http\Controllers;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\SiteVisit\Http\Requests\CheckInSiteVisitRequest;
use App\Domain\SiteVisit\Http\Requests\StoreSiteVisitRequest;
use App\Domain\SiteVisit\Http\Requests\UpdateSiteVisitRequest;
use App\Domain\SiteVisit\Models\SiteVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteVisitController
{
    public function index(ValuationAssignment $assignment): JsonResponse
    {
        request()->user()->can('view', $assignment) || abort(403);

        return response()->json(['data' => $assignment->siteVisits()->with('photos')->orderByDesc('scheduled_at')->get()]);
    }

    public function store(StoreSiteVisitRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $visit = SiteVisit::create([
            'tenant_id' => $assignment->tenant_id,
            'valuation_assignment_id' => $assignment->id,
            'property_id' => $request->input('property_id'),
            'scheduled_at' => $request->input('scheduled_at'),
            'status' => 'scheduled',
            'sync_status' => 'synced',
        ]);

        return response()->json(['data' => $visit], 201);
    }

    public function checkIn(CheckInSiteVisitRequest $request, SiteVisit $siteVisit): JsonResponse
    {
        $siteVisit->forceFill([
            'checked_in_at' => now(),
            'check_in_latitude' => $request->input('check_in_latitude'),
            'check_in_longitude' => $request->input('check_in_longitude'),
            'status' => 'in_progress',
        ])->save();

        return response()->json(['data' => $siteVisit->fresh()]);
    }

    public function update(UpdateSiteVisitRequest $request, SiteVisit $siteVisit): JsonResponse
    {
        $siteVisit->update($request->validated());

        return response()->json(['data' => $siteVisit->fresh()]);
    }

    /** Section 17: "Prevent inspection completion if mandatory information is missing." */
    public function complete(Request $request, SiteVisit $siteVisit): JsonResponse
    {
        $request->user()->can('assignments.update') || abort(403);

        if (! $siteVisit->canBeMarkedComplete()) {
            return response()->json([
                'errors' => [['status' => '422', 'title' => 'IncompleteInspection', 'detail' => 'Cannot mark this inspection complete -- check-in, owner representative confirmation, field checklist, and GPS coordinates are all required first.']],
            ], 422);
        }

        $siteVisit->forceFill([
            'inspection_completed' => true,
            'inspection_completed_at' => now(),
            'status' => 'completed',
        ])->save();

        return response()->json(['data' => $siteVisit->fresh()]);
    }

    public function show(SiteVisit $siteVisit): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        return response()->json(['data' => $siteVisit->load('photos')]);
    }
}
