<?php

declare(strict_types=1);

namespace App\Domain\Building\Http\Controllers;

use App\Domain\Building\Http\Requests\StoreBuildingConditionAssessmentRequest;
use App\Domain\Building\Models\Building;
use App\Domain\Building\Models\BuildingConditionAssessment;
use App\Domain\Building\Models\BuildingConditionAssessmentItem;
use App\Domain\Building\Services\BuildingConditionToDepreciationMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BuildingConditionAssessmentController
{
    public function store(StoreBuildingConditionAssessmentRequest $request, Building $building): JsonResponse
    {
        $assessment = DB::transaction(function () use ($request, $building) {
            $assessment = BuildingConditionAssessment::create([
                'tenant_id' => $building->tenant_id,
                'building_id' => $building->id,
                'assessed_by_user_id' => $request->user()->id,
                'assessed_at' => now(),
                ...$request->safe()->except('items'),
            ]);

            foreach ($request->input('items') as $item) {
                BuildingConditionAssessmentItem::create([
                    'tenant_id' => $building->tenant_id,
                    'building_condition_assessment_id' => $assessment->id,
                    'item_type' => $item['item_type'],
                    'condition_rating' => $item['condition_rating'],
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            return $assessment;
        });

        return response()->json(['data' => $assessment->load('items')], 201);
    }

    public function show(Building $building): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        $assessment = $building->latestConditionAssessment()->with('items')->first();

        return response()->json(['data' => $assessment]);
    }

    /** Preview the suggested cost-approach depreciation inputs this building's latest assessment would produce. */
    public function suggestedDepreciation(Building $building, BuildingConditionToDepreciationMapper $mapper): JsonResponse
    {
        request()->user()->can('valuations.create') || abort(403);

        $assessment = $building->latestConditionAssessment()->with('items')->first();

        if ($assessment === null) {
            return response()->json(['data' => null, 'message' => 'No condition assessment recorded for this building yet.']);
        }

        return response()->json(['data' => $mapper->map($assessment)]);
    }
}
