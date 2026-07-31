<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Http\Controllers;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Valuation\Http\Requests\BuildingCostEstimationRequest;
use App\Domain\Valuation\Http\Requests\CostApproachRequest;
use App\Domain\Valuation\Http\Requests\MarketComparisonRequest;
use App\Domain\Valuation\Http\Requests\ValuationCertificateSummaryRequest;
use App\Domain\Valuation\Http\Requests\VehicleValuationRequest;
use App\Domain\Valuation\Http\Requests\WeightedLandRateRequest;
use App\Domain\Valuation\Http\Resources\ValuationCalculationResource;
use App\Domain\Valuation\Models\ValuationCalculation;
use App\Domain\Valuation\Services\ValuationCalculationService;
use App\Domain\Valuation\Services\ValuationCertificateSummaryService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Reference implementation for triggering a valuation engine and persisting
 * its result. Income-approach and residual-method endpoints follow this
 * exact same shape (Request -> assignment ownership check -> Service ->
 * Resource) and are the next controllers to add in this pattern.
 */
class ValuationCalculationController
{
    public function __construct(private readonly ValuationCalculationService $service) {}

    public function marketComparison(MarketComparisonRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $request->user()->can('view', $assignment) || abort(403);

        try {
            $calculation = $this->service->runMarketComparison(
                tenantId: $request->user()->tenant_id,
                assignmentId: $assignment->id,
                propertyId: $request->input('property_id'),
                comparablesInput: $request->only('comparables'),
                calculatedByUserId: $request->user()->id,
            );
        } catch (RuntimeException|\InvalidArgumentException $e) {
            return $this->calculationError($e);
        }

        return (new ValuationCalculationResource($calculation))->response()->setStatusCode(201);
    }

    public function costApproach(CostApproachRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $request->user()->can('view', $assignment) || abort(403);

        try {
            $calculation = $this->service->runCostApproach(
                tenantId: $request->user()->tenant_id,
                assignmentId: $assignment->id,
                propertyId: $request->input('property_id'),
                buildingId: $request->input('building_id'),
                input: $request->except(['property_id', 'building_id']),
                calculatedByUserId: $request->user()->id,
            );
        } catch (RuntimeException|\InvalidArgumentException $e) {
            return $this->calculationError($e);
        }

        return (new ValuationCalculationResource($calculation))->response()->setStatusCode(201);
    }

    private function calculationError(\Throwable $e): JsonResponse
    {
        return response()->json([
            'errors' => [['status' => '422', 'title' => 'InvalidCalculationInput', 'detail' => $e->getMessage()]],
        ], 422);
    }

    public function weightedLandRate(WeightedLandRateRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $request->user()->can('view', $assignment) || abort(403);

        try {
            $calculation = $this->service->runWeightedLandRate(
                tenantId: $request->user()->tenant_id,
                assignmentId: $assignment->id,
                propertyId: $request->input('property_id'),
                input: $request->only('plots'),
                calculatedByUserId: $request->user()->id,
            );
        } catch (RuntimeException|\InvalidArgumentException $e) {
            return $this->calculationError($e);
        }

        return (new ValuationCalculationResource($calculation))->response()->setStatusCode(201);
    }

    public function vehicleValuation(VehicleValuationRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $request->user()->can('view', $assignment) || abort(403);

        try {
            $calculation = $this->service->runVehicleValuation(
                tenantId: $request->user()->tenant_id,
                assignmentId: $assignment->id,
                input: $request->validated(),
                calculatedByUserId: $request->user()->id,
            );
        } catch (RuntimeException|\InvalidArgumentException $e) {
            return $this->calculationError($e);
        }

        return (new ValuationCalculationResource($calculation))->response()->setStatusCode(201);
    }

    public function buildingCostEstimation(BuildingCostEstimationRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $request->user()->can('view', $assignment) || abort(403);

        try {
            $calculation = $this->service->runBuildingCostEstimation(
                tenantId: $request->user()->tenant_id,
                assignmentId: $assignment->id,
                propertyId: $request->input('property_id'),
                buildingId: $request->input('building_id'),
                input: $request->only(['floors', 'age_years', 'sanitary_fixture_pct', 'electrical_fixture_pct', 'depreciation_pct_per_annum']),
                calculatedByUserId: $request->user()->id,
            );
        } catch (RuntimeException|\InvalidArgumentException $e) {
            return $this->calculationError($e);
        }

        return (new ValuationCalculationResource($calculation))->response()->setStatusCode(201);
    }

    public function certificateSummary(
        ValuationCertificateSummaryRequest $request,
        ValuationAssignment $assignment,
        ValuationCertificateSummaryService $summaryService,
    ): JsonResponse {
        $request->user()->can('view', $assignment) || abort(403);

        $client = $assignment->client;

        $fmv = $request->input('weighted_fair_market_value');
        if ($fmv === null) {
            $latest = ValuationCalculation::where('valuation_assignment_id', $assignment->id)
                ->where('method', 'weighted_land_rate')
                ->latest('calculated_at')
                ->first();

            if ($latest === null) {
                return $this->calculationError(new RuntimeException(
                    'No weighted_fair_market_value was provided and no weighted-land-rate calculation exists yet for this assignment to fall back to.'
                ));
            }

            $fmv = (float) $latest->computed_value;
        }

        $govtPct = $request->input('government_weight_pct') ?? $client?->land_rate_government_weight_pct ?? 30.0;
        $marketPct = $request->input('market_weight_pct') ?? $client?->land_rate_market_weight_pct ?? 70.0;
        $distressPct = $request->input('distress_value_pct') ?? $client?->distress_value_pct ?? 80.0;

        $summary = $summaryService->generate([
            'weighted_fair_market_value' => (float) $fmv,
            'government_weight_pct' => (float) $govtPct,
            'market_weight_pct' => (float) $marketPct,
            'distress_value_pct' => (float) $distressPct,
            'inspection_date' => $request->input('inspection_date'),
            'comments' => $request->input('comments'),
        ]);

        return response()->json(['data' => $summary]);
    }
}
