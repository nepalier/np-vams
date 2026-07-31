<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Services;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Comparable\Models\ComparableAdjustment;
use App\Domain\Valuation\Models\ValuationCalculation;
use App\Domain\Valuation\Models\ValuationCalculationItem;
use Illuminate\Support\Facades\DB;

/**
 * Persistence wrapper around the pure calculation engines. Keeping the
 * engines themselves DB-free (see MarketComparisonEngine etc.) means they
 * stay trivially unit-testable; this class is the only place that writes
 * an engine's output to valuation_calculations / valuation_calculation_items,
 * so `computed_value` in the database is always exactly what the engine
 * returned -- never hand-adjusted in a controller along the way.
 */
class ValuationCalculationService
{
    public function __construct(
        private readonly MarketComparisonEngine $marketComparisonEngine,
        private readonly CostApproachEngine $costApproachEngine,
        private readonly IncomeApproachEngine $incomeApproachEngine,
        private readonly ResidualEngine $residualEngine,
        private readonly WeightedLandRateEngine $weightedLandRateEngine,
        private readonly VehicleValuationEngine $vehicleValuationEngine,
        private readonly BuildingCostEstimationEngine $buildingCostEstimationEngine,
    ) {}

    public function runMarketComparison(
        string $tenantId,
        string $assignmentId,
        ?string $propertyId,
        array $comparablesInput,
        ?string $calculatedByUserId,
    ): ValuationCalculation {
        $result = $this->marketComparisonEngine->calculate($comparablesInput['comparables']);

        return DB::transaction(function () use ($tenantId, $assignmentId, $propertyId, $comparablesInput, $result, $calculatedByUserId) {
            $calculation = ValuationCalculation::create([
                'tenant_id' => $tenantId,
                'valuation_assignment_id' => $assignmentId,
                'property_id' => $propertyId,
                'method' => 'market_comparison',
                'status' => 'draft',
                'input_snapshot' => $comparablesInput,
                'computed_value' => $result['suggested_adopted_rate'],
                'computed_details' => $result,
                'calculated_by_user_id' => $calculatedByUserId,
                'calculated_at' => now(),
            ]);

            foreach ($result['per_comparable'] as $row) {
                ValuationCalculationItem::create([
                    'tenant_id' => $tenantId,
                    'valuation_calculation_id' => $calculation->id,
                    'item_type' => 'comparable',
                    'reference_id' => $comparablesInput['comparables'][$row['index']]['comparable_property_id'] ?? null,
                    'label' => "Comparable #{$row['index']}",
                    'rate' => $row['base_rate'],
                    'adjustment_factor' => $row['combined_factor'],
                    'amount' => $row['adjusted_rate'],
                    'sequence' => $row['index'],
                ]);

                if (! empty($comparablesInput['comparables'][$row['index']]['comparable_property_id'])) {
                    ComparableAdjustment::create([
                        'tenant_id' => $tenantId,
                        'valuation_calculation_id' => $calculation->id,
                        'comparable_property_id' => $comparablesInput['comparables'][$row['index']]['comparable_property_id'],
                        'weight' => $row['weight'],
                        'adjustment_factors' => $row['factors'],
                        'adjusted_unit_rate' => $row['adjusted_rate'],
                    ]);
                }
            }

            return $calculation->load('items');
        });
    }

    public function runCostApproach(
        string $tenantId,
        string $assignmentId,
        ?string $propertyId,
        ?string $buildingId,
        array $input,
        ?string $calculatedByUserId,
    ): ValuationCalculation {
        $result = $this->costApproachEngine->calculate($input);

        return ValuationCalculation::create([
            'tenant_id' => $tenantId,
            'valuation_assignment_id' => $assignmentId,
            'property_id' => $propertyId,
            'building_id' => $buildingId,
            'method' => 'cost_approach',
            'status' => 'draft',
            'input_snapshot' => $input,
            'computed_value' => $result['depreciated_value'],
            'computed_details' => $result,
            'calculated_by_user_id' => $calculatedByUserId,
            'calculated_at' => now(),
        ]);
    }

    public function runIncomeApproach(
        string $tenantId,
        string $assignmentId,
        ?string $propertyId,
        array $input,
        ?string $calculatedByUserId,
    ): ValuationCalculation {
        $result = $this->incomeApproachEngine->directCapitalization($input);

        return ValuationCalculation::create([
            'tenant_id' => $tenantId,
            'valuation_assignment_id' => $assignmentId,
            'property_id' => $propertyId,
            'method' => 'income_approach',
            'status' => 'draft',
            'input_snapshot' => $input,
            'computed_value' => $result['capital_value'],
            'computed_details' => $result,
            'calculated_by_user_id' => $calculatedByUserId,
            'calculated_at' => now(),
        ]);
    }

    public function runResidual(
        string $tenantId,
        string $assignmentId,
        ?string $propertyId,
        array $input,
        ?string $calculatedByUserId,
    ): ValuationCalculation {
        $result = $this->residualEngine->calculate($input);

        return ValuationCalculation::create([
            'tenant_id' => $tenantId,
            'valuation_assignment_id' => $assignmentId,
            'property_id' => $propertyId,
            'method' => 'residual',
            'status' => 'draft',
            'input_snapshot' => $input,
            'computed_value' => $result['residual_land_value'],
            'computed_details' => $result,
            'calculated_by_user_id' => $calculatedByUserId,
            'calculated_at' => now(),
        ]);
    }

    public function runWeightedLandRate(
        string $tenantId,
        string $assignmentId,
        ?string $propertyId,
        array $input,
        ?string $calculatedByUserId,
    ): ValuationCalculation {
        $engine = $this->resolveWeightedLandRateEngine($assignmentId, $input);
        $result = $engine->calculate($input['plots']);

        return ValuationCalculation::create([
            'tenant_id' => $tenantId,
            'valuation_assignment_id' => $assignmentId,
            'property_id' => $propertyId,
            'method' => 'weighted_land_rate',
            'status' => 'draft',
            'input_snapshot' => $input,
            'computed_value' => $result['total_land_value'],
            'computed_details' => $result,
            'calculated_by_user_id' => $calculatedByUserId,
            'calculated_at' => now(),
        ]);
    }

    /**
     * Weighting resolution order, most specific wins:
     *   1. Explicit government_weight_pct/market_weight_pct in THIS request
     *      (a valuer overriding for one specific calculation)
     *   2. The assignment's client's configured convention (a bank's own
     *      standing guideline -- see clients.land_rate_government_weight_pct)
     *   3. WeightedLandRateEngine's own constructor default (30/70)
     *
     * Real reference documents showed three different bank conventions
     * (30/70, 70/30, 20/80) -- none of them is "the" default, so this
     * resolution order is what keeps the right one applied per bank
     * without a valuer having to remember and retype it every time.
     */
    private function resolveWeightedLandRateEngine(string $assignmentId, array $input): WeightedLandRateEngine
    {
        if (isset($input['government_weight_pct'], $input['market_weight_pct'])) {
            return new WeightedLandRateEngine(
                governmentWeightPct: (float) $input['government_weight_pct'],
                marketWeightPct: (float) $input['market_weight_pct'],
            );
        }

        $assignment = ValuationAssignment::find($assignmentId);
        $client = $assignment?->client;

        if ($client !== null && $client->land_rate_government_weight_pct !== null && $client->land_rate_market_weight_pct !== null) {
            return new WeightedLandRateEngine(
                governmentWeightPct: (float) $client->land_rate_government_weight_pct,
                marketWeightPct: (float) $client->land_rate_market_weight_pct,
            );
        }

        $tenant = $assignment?->tenant;

        if ($tenant !== null && $tenant->default_land_rate_government_weight_pct !== null && $tenant->default_land_rate_market_weight_pct !== null) {
            return new WeightedLandRateEngine(
                governmentWeightPct: (float) $tenant->default_land_rate_government_weight_pct,
                marketWeightPct: (float) $tenant->default_land_rate_market_weight_pct,
            );
        }

        return $this->weightedLandRateEngine;
    }

    private function resolveVehicleValuationEngine(string $assignmentId, array $input): VehicleValuationEngine
    {
        if (isset($input['scrap_pct'], $input['depreciation_pct_per_annum'], $input['other_cost_pct_per_annum'])) {
            return new VehicleValuationEngine(
                scrapDeductionPct: (float) $input['scrap_pct'],
                depreciationPctPerAnnum: (float) $input['depreciation_pct_per_annum'],
                otherCostPctPerAnnum: (float) $input['other_cost_pct_per_annum'],
            );
        }

        $tenant = ValuationAssignment::find($assignmentId)?->tenant;

        if ($tenant !== null
            && $tenant->default_vehicle_scrap_pct !== null
            && $tenant->default_vehicle_depreciation_pct_per_annum !== null
            && $tenant->default_vehicle_other_cost_pct_per_annum !== null
        ) {
            return new VehicleValuationEngine(
                scrapDeductionPct: (float) $tenant->default_vehicle_scrap_pct,
                depreciationPctPerAnnum: (float) $tenant->default_vehicle_depreciation_pct_per_annum,
                otherCostPctPerAnnum: (float) $tenant->default_vehicle_other_cost_pct_per_annum,
            );
        }

        return $this->vehicleValuationEngine;
    }

    private function resolveBuildingCostEstimationEngine(string $assignmentId, array $input): BuildingCostEstimationEngine
    {
        if (isset($input['sanitary_fixture_pct'], $input['electrical_fixture_pct'], $input['depreciation_pct_per_annum'])) {
            return new BuildingCostEstimationEngine(
                sanitaryFixturePct: (float) $input['sanitary_fixture_pct'],
                electricalFixturePct: (float) $input['electrical_fixture_pct'],
                depreciationPctPerAnnum: (float) $input['depreciation_pct_per_annum'],
            );
        }

        $tenant = ValuationAssignment::find($assignmentId)?->tenant;

        if ($tenant !== null
            && $tenant->default_building_sanitary_fixture_pct !== null
            && $tenant->default_building_electrical_fixture_pct !== null
            && $tenant->default_building_depreciation_pct_per_annum !== null
        ) {
            return new BuildingCostEstimationEngine(
                sanitaryFixturePct: (float) $tenant->default_building_sanitary_fixture_pct,
                electricalFixturePct: (float) $tenant->default_building_electrical_fixture_pct,
                depreciationPctPerAnnum: (float) $tenant->default_building_depreciation_pct_per_annum,
            );
        }

        return $this->buildingCostEstimationEngine;
    }

    public function runVehicleValuation(
        string $tenantId,
        string $assignmentId,
        array $input,
        ?string $calculatedByUserId,
    ): ValuationCalculation {
        $result = $this->resolveVehicleValuationEngine($assignmentId, $input)->calculate(
            currentMarketPriceOfNew: (float) $input['current_market_price_of_new'],
            ageYears: (float) $input['age_years'],
            otherReducingFactors: (float) ($input['other_reducing_factors'] ?? 0),
        );

        return ValuationCalculation::create([
            'tenant_id' => $tenantId,
            'valuation_assignment_id' => $assignmentId,
            'property_id' => null, // vehicles/machinery are not tied to a land property
            'method' => 'vehicle',
            'status' => 'draft',
            'input_snapshot' => $input,
            'computed_value' => $result['net_fair_market_value'],
            'computed_details' => $result,
            'calculated_by_user_id' => $calculatedByUserId,
            'calculated_at' => now(),
        ]);
    }

    public function runBuildingCostEstimation(
        string $tenantId,
        string $assignmentId,
        ?string $propertyId,
        ?string $buildingId,
        array $input,
        ?string $calculatedByUserId,
    ): ValuationCalculation {
        $result = $this->resolveBuildingCostEstimationEngine($assignmentId, $input)->calculate(
            floors: $input['floors'],
            ageYears: (float) $input['age_years'],
        );

        return ValuationCalculation::create([
            'tenant_id' => $tenantId,
            'valuation_assignment_id' => $assignmentId,
            'property_id' => $propertyId,
            'building_id' => $buildingId,
            'method' => 'building_cost_estimation',
            'status' => 'draft',
            'input_snapshot' => $input,
            'computed_value' => $result['actual_construction_cost'],
            'computed_details' => $result,
            'calculated_by_user_id' => $calculatedByUserId,
            'calculated_at' => now(),
        ]);
    }
}
