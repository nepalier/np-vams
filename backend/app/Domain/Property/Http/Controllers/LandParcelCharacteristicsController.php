<?php

declare(strict_types=1);

namespace App\Domain\Property\Http\Controllers;

use App\Domain\Property\Http\Requests\UpdateLandParcelCharacteristicsRequest;
use App\Domain\Property\Models\LandParcel;
use App\Domain\Property\Models\LandParcelCharacteristics;
use App\Domain\Property\Services\LandAdjustmentFactorMapper;
use Illuminate\Http\JsonResponse;

class LandParcelCharacteristicsController
{
    public function show(LandParcel $parcel): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        return response()->json(['data' => $parcel->load('characteristics')->characteristics]);
    }

    public function update(UpdateLandParcelCharacteristicsRequest $request, LandParcel $parcel): JsonResponse
    {
        $characteristics = LandParcelCharacteristics::updateOrCreate(
            ['tenant_id' => $parcel->tenant_id, 'land_parcel_id' => $parcel->id],
            $request->validated(),
        );

        return response()->json(['data' => $characteristics]);
    }

    /** Preview the suggested market-comparison adjustment factors this parcel's recorded characteristics would produce. */
    public function suggestedAdjustmentFactors(LandParcel $parcel, LandAdjustmentFactorMapper $mapper): JsonResponse
    {
        request()->user()->can('valuations.create') || abort(403);

        return response()->json(['data' => $mapper->map($parcel->load('characteristics'))]);
    }
}
