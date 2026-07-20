<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Services;

use App\Domain\Valuation\Enums\DepreciationMethod;
use InvalidArgumentException;

/**
 * Section 23: "Building value = Replacement cost new - physical
 * depreciation - functional obsolescence - economic obsolescence".
 */
class CostApproachEngine
{
    /**
     * @param  array{
     *   built_up_area_sqm: float, base_construction_rate: float,
     *   location_factor?: float, transportation_factor?: float,
     *   material_factor?: float, labour_factor?: float,
     *   professional_fee_pct?: float, external_works_amount?: float,
     *   service_cost_amount?: float, completion_percentage?: float,
     *   depreciation_method: string,
     *   age_years?: float, economic_life_years?: float, max_depreciation_pct?: float,
     *   physical_depreciation_pct?: float,
     *   functional_obsolescence_amount?: float, economic_obsolescence_amount?: float,
     *   components?: array<int, array{amount: float, depreciation_pct: float}>,
     * }  $input
     */
    public function calculate(array $input): array
    {
        $builtUpArea = $input['built_up_area_sqm'];
        $baseRate = $input['base_construction_rate'];

        if ($builtUpArea < 0 || $baseRate < 0) {
            throw new InvalidArgumentException('Built-up area and base construction rate must be non-negative.');
        }

        $locationFactor = $input['location_factor'] ?? 1.0;
        $transportationFactor = $input['transportation_factor'] ?? 1.0;
        $materialFactor = $input['material_factor'] ?? 1.0;
        $labourFactor = $input['labour_factor'] ?? 1.0;
        $professionalFeePct = $input['professional_fee_pct'] ?? 0.0;
        $externalWorks = $input['external_works_amount'] ?? 0.0;
        $serviceCost = $input['service_cost_amount'] ?? 0.0;
        $completionPct = $input['completion_percentage'] ?? 100.0;

        $baseCost = $builtUpArea * $baseRate * $locationFactor * $transportationFactor * $materialFactor * $labourFactor;
        $baseCostWithFee = $baseCost * (1 + $professionalFeePct / 100);
        $replacementCostNew = round($baseCostWithFee + $externalWorks + $serviceCost, 2);

        if ($completionPct < 100) {
            $replacementCostNew = round($replacementCostNew * ($completionPct / 100), 2);
        }

        [$physicalDepreciationAmount, $depreciationDetails] = $this->computePhysicalDepreciation($input, $replacementCostNew);

        $functionalObsolescence = $input['functional_obsolescence_amount'] ?? 0.0;
        $economicObsolescence = $input['economic_obsolescence_amount'] ?? 0.0;

        // Section 31 validation rule "Negative depreciation" -- guarded here
        // at the source of truth, not just in a form validator, since this
        // engine may also be called from a queued recalculation job.
        if ($physicalDepreciationAmount < 0 || $functionalObsolescence < 0 || $economicObsolescence < 0) {
            throw new InvalidArgumentException('Depreciation and obsolescence amounts cannot be negative.');
        }

        $depreciatedValue = max(
            0.0,
            round($replacementCostNew - $physicalDepreciationAmount - $functionalObsolescence - $economicObsolescence, 2)
        );

        return [
            'replacement_cost_new' => $replacementCostNew,
            'physical_depreciation_amount' => round($physicalDepreciationAmount, 2),
            'functional_obsolescence_amount' => round($functionalObsolescence, 2),
            'economic_obsolescence_amount' => round($economicObsolescence, 2),
            'depreciated_value' => $depreciatedValue,
            'depreciation_method' => $input['depreciation_method'],
            'depreciation_details' => $depreciationDetails,
        ];
    }

    private function computePhysicalDepreciation(array $input, float $replacementCostNew): array
    {
        $method = DepreciationMethod::from($input['depreciation_method']);
        $maxDepreciationPct = $input['max_depreciation_pct'] ?? 80.0;

        return match ($method) {
            DepreciationMethod::StraightLine, DepreciationMethod::AgeLife => $this->ageBasedDepreciation(
                $input, $replacementCostNew, $maxDepreciationPct
            ),
            DepreciationMethod::ObservedCondition, DepreciationMethod::CustomProfessional => $this->directPercentageDepreciation(
                $input, $replacementCostNew, $maxDepreciationPct
            ),
            DepreciationMethod::ComponentWise => $this->componentWiseDepreciation($input),
        };
    }

    private function ageBasedDepreciation(array $input, float $replacementCostNew, float $maxPct): array
    {
        $age = $input['age_years'] ?? throw new InvalidArgumentException('age_years is required for age-based depreciation.');
        $economicLife = $input['economic_life_years'] ?? throw new InvalidArgumentException('economic_life_years is required for age-based depreciation.');

        if ($economicLife <= 0) {
            throw new InvalidArgumentException('economic_life_years must be positive.');
        }

        $ratio = min($age / $economicLife, $maxPct / 100);
        $amount = round($replacementCostNew * $ratio, 2);

        return [$amount, ['age_years' => $age, 'economic_life_years' => $economicLife, 'applied_ratio' => round($ratio, 4)]];
    }

    private function directPercentageDepreciation(array $input, float $replacementCostNew, float $maxPct): array
    {
        $pct = $input['physical_depreciation_pct']
            ?? throw new InvalidArgumentException('physical_depreciation_pct is required for this depreciation method.');

        $cappedPct = min($pct, $maxPct);
        $amount = round($replacementCostNew * ($cappedPct / 100), 2);

        return [$amount, ['applied_pct' => $cappedPct, 'requested_pct' => $pct, 'capped' => $pct > $maxPct]];
    }

    private function componentWiseDepreciation(array $input): array
    {
        $components = $input['components'] ?? throw new InvalidArgumentException('components array is required for component-wise depreciation.');

        $total = 0.0;
        $details = [];

        foreach ($components as $component) {
            $componentDepreciation = round($component['amount'] * ($component['depreciation_pct'] / 100), 2);
            $total += $componentDepreciation;
            $details[] = [
                'amount' => $component['amount'],
                'depreciation_pct' => $component['depreciation_pct'],
                'depreciation_amount' => $componentDepreciation,
            ];
        }

        return [$total, ['components' => $details]];
    }
}
