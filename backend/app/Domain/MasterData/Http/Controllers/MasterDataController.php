<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Http\Controllers;

use App\Domain\MasterData\Models\AreaUnit;
use App\Domain\MasterData\Models\District;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\PropertyType;
use App\Domain\MasterData\Models\ValuationPurpose;
use Illuminate\Http\JsonResponse;

/**
 * Lightweight read-only listing endpoints for populating frontend
 * dropdowns -- deliberately NOT full master-data CRUD (Section 38's admin
 * import/export/versioning tooling is a separate, larger piece); this is
 * just "give the UI something to populate a <select> with" for the
 * reference tables that were seeded but had no way to be listed via API.
 */
class MasterDataController
{
    public function valuationPurposes(): JsonResponse
    {
        return response()->json(['data' => ValuationPurpose::where('is_active', true)->orderBy('name_en')->get(['id', 'code', 'name_en', 'name_ne'])]);
    }

    public function propertyTypes(): JsonResponse
    {
        return response()->json(['data' => PropertyType::where('is_active', true)->orderBy('name_en')->get(['id', 'code', 'name_en', 'name_ne'])]);
    }

    public function areaUnits(): JsonResponse
    {
        return response()->json(['data' => AreaUnit::orderBy('name_en')->get(['id', 'code', 'name_en', 'name_ne', 'conversion_to_sqm'])]);
    }

    public function districts(): JsonResponse
    {
        return response()->json(['data' => District::orderBy('name_en')->get(['id', 'province_id', 'name_en', 'name_ne'])]);
    }

    public function fiscalYears(): JsonResponse
    {
        return response()->json(['data' => FiscalYear::orderByDesc('starts_on')->get(['id', 'code_bs', 'is_current'])]);
    }
}
