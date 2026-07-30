<?php

declare(strict_types=1);

namespace App\Domain\Building\Http\Controllers;

use App\Domain\Building\Http\Requests\StoreBuildingRequest;
use App\Domain\Building\Models\Building;
use App\Domain\Building\Models\BuildingFloor;
use App\Domain\Property\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BuildingController
{
    public function index(Property $property): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        return response()->json(['data' => $property->buildings()->with('floors')->get()]);
    }

    public function store(StoreBuildingRequest $request, Property $property): JsonResponse
    {
        $building = DB::transaction(function () use ($request, $property) {
            $building = Building::create([
                'tenant_id' => $property->tenant_id,
                'property_id' => $property->id,
                ...$request->safe()->except('floors'),
            ]);

            foreach ($request->input('floors', []) as $floor) {
                BuildingFloor::create([
                    'tenant_id' => $property->tenant_id,
                    'building_id' => $building->id,
                    ...$floor,
                ]);
            }

            return $building;
        });

        return response()->json(['data' => $building->load('floors')], 201);
    }

    public function show(Building $building): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        return response()->json(['data' => $building->load(['floors', 'latestConditionAssessment'])]);
    }
}
