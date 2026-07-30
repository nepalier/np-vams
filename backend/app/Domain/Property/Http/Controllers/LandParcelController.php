<?php

declare(strict_types=1);

namespace App\Domain\Property\Http\Controllers;

use App\Domain\MasterData\Models\AreaUnit;
use App\Domain\Property\Http\Requests\StoreLandParcelRequest;
use App\Domain\Property\Models\LandParcel;
use App\Domain\Property\Models\Property;
use App\Support\ValueObjects\Area;
use Illuminate\Http\JsonResponse;

class LandParcelController
{
    public function index(Property $property): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        return response()->json(['data' => $property->parcels()->with(['characteristics', 'planning'])->get()]);
    }

    public function store(StoreLandParcelRequest $request, Property $property): JsonResponse
    {
        $data = $request->validated();

        // Section 10: "Implement automatic conversion while preserving
        // original entered values" -- the originally entered value/unit is
        // stored exactly as given; area_*_sqm is derived here via the
        // real Area value object (Phase 3), never left for the caller to
        // compute or guess at client-side.
        if (! empty($data['area_lalpurja']) && ! empty($data['area_lalpurja_unit_id'])) {
            $unit = AreaUnit::findOrFail($data['area_lalpurja_unit_id']);
            $data['area_lalpurja_sqm'] = Area::from((float) $data['area_lalpurja'], $unit)->squareMetres;
        }

        if (! empty($data['area_site_measured']) && ! empty($data['area_site_measured_unit_id'])) {
            $unit = AreaUnit::findOrFail($data['area_site_measured_unit_id']);
            $data['area_site_measured_sqm'] = Area::from((float) $data['area_site_measured'], $unit)->squareMetres;
        }

        $parcel = LandParcel::create([
            'tenant_id' => $property->tenant_id,
            'property_id' => $property->id,
            ...$data,
        ]);

        return response()->json(['data' => $parcel], 201);
    }

    public function show(LandParcel $parcel): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        return response()->json(['data' => $parcel->load(['characteristics', 'planning'])]);
    }
}
