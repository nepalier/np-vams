<?php

declare(strict_types=1);

namespace App\Domain\Rates\Http\Controllers;

use App\Domain\Rates\Http\Requests\StoreGovernmentLandRateRequest;
use App\Domain\Rates\Models\GovernmentLandRate;
use App\Domain\Rates\Services\GovernmentLandRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GovernmentLandRateController
{
    public function __construct(private readonly GovernmentLandRateService $service) {}

    public function index(Request $request): JsonResponse
    {
        $rates = GovernmentLandRate::query()
            ->when($request->filled('fiscal_year_id'), fn ($q) => $q->where('fiscal_year_id', $request->integer('fiscal_year_id')))
            ->when($request->filled('district_id'), fn ($q) => $q->where('district_id', $request->integer('district_id')))
            ->when($request->boolean('current_only', true), fn ($q) => $q->where('is_current', true))
            ->orderByDesc('effective_date')
            ->paginate($request->integer('per_page', 50));

        return response()->json($rates);
    }

    public function store(StoreGovernmentLandRateRequest $request): JsonResponse
    {
        $rate = $this->service->create($request->validated());

        return response()->json(['data' => $rate], 201);
    }

    public function createNewVersion(StoreGovernmentLandRateRequest $request, GovernmentLandRate $governmentLandRate): JsonResponse
    {
        $newVersion = $this->service->createNewVersion($governmentLandRate, $request->validated());

        return response()->json(['data' => $newVersion], 201);
    }

    /** The lookup the Weighted Land Rate calculation form actually needs. */
    public function suggestedRate(Request $request): JsonResponse
    {
        $rate = $this->service->findCurrentRate(
            fiscalYearId: $request->integer('fiscal_year_id'),
            districtId: $request->integer('district_id'),
            localLevelId: $request->filled('local_level_id') ? $request->integer('local_level_id') : null,
            wardId: $request->filled('ward_id') ? $request->integer('ward_id') : null,
            landCategory: $request->input('land_category'),
        );

        return response()->json(['data' => $rate]);
    }
}
