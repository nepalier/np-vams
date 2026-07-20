<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Http\Controllers;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Valuation\Http\Requests\CostApproachRequest;
use App\Domain\Valuation\Http\Requests\MarketComparisonRequest;
use App\Domain\Valuation\Http\Resources\ValuationCalculationResource;
use App\Domain\Valuation\Services\ValuationCalculationService;
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
}
