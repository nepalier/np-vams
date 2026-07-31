<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Services;

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
        $result = $this->weightedLandRateEngine->calculate($input['plots']);

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

    public function runVehicleValuation(
        string $tenantId,
        string $assignmentId,
        array $input,
        ?string $calculatedByUserId,
    ): ValuationCalculation {
        $result = $this->vehicleValuationEngine->calculate(
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
}
