<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Services;

use InvalidArgumentException;

/**
 * Section 26: reconcile results from multiple valuation methods into a
 * single market value, then derive the dependent values (distress,
 * forced-sale, mortgage, insurance...) from tenant/client-configured
 * percentages -- never a hard-coded national figure (Section 49).
 */
class ReconciliationService
{
    /**
     * @param  array<int, array{method: string, value: float, reliability_rating: float, weight?: float}>  $methodResults
     *         weight defaults to reliability_rating when not supplied, so a method the valuer trusts more
     *         naturally pulls the reconciled figure toward it without a separate weighting step.
     * @param  int  $roundingUnit  nearest currency unit to round the market value to, e.g. 1000
     */
    public function reconcile(
        array $methodResults,
        int $roundingUnit = 1000,
        ?float $manualOverrideValue = null,
        ?string $overrideJustification = null,
    ): array {
        if (count($methodResults) === 0) {
            throw new InvalidArgumentException('At least one method result is required to reconcile.');
        }

        $weightSum = 0.0;
        $weightedSum = 0.0;

        foreach ($methodResults as $result) {
            $weight = $result['weight'] ?? $result['reliability_rating'];
            $weightSum += $weight;
            $weightedSum += $result['value'] * $weight;
        }

        if ($weightSum <= 0) {
            throw new InvalidArgumentException('Sum of method weights must be positive.');
        }

        $computedValue = round($weightedSum / $weightSum, 2);

        $isOverridden = $manualOverrideValue !== null;

        if ($isOverridden && empty($overrideJustification)) {
            // Section 26: "Require justification for manual overrides."
            throw new InvalidArgumentException('A justification is required when manually overriding the reconciled value.');
        }

        $reconciledValue = $isOverridden ? $manualOverrideValue : $computedValue;
        $roundedValue = $roundingUnit > 0
            ? (float) (round($reconciledValue / $roundingUnit) * $roundingUnit)
            : $reconciledValue;

        return [
            'method_results' => $methodResults,
            'computed_weighted_value' => $computedValue,
            'is_manual_override' => $isOverridden,
            'override_justification' => $overrideJustification,
            'reconciled_market_value' => $reconciledValue,
            'rounded_market_value' => $roundedValue,
        ];
    }

    /**
     * Derives distress/forced-sale/mortgage/insurance figures from
     * caller-supplied percentages (sourced from client/bank config per
     * Section 28 -- "Do not hard-code one national LTV percentage").
     *
     * @param  array{distress_pct?: float, forced_sale_pct?: float, mortgage_haircut_pct?: float, insurance_pct?: float}  $percentages
     */
    public function deriveDependentValues(float $marketValue, array $percentages): array
    {
        $distressPct = $percentages['distress_pct'] ?? null;
        $forcedSalePct = $percentages['forced_sale_pct'] ?? null;
        $mortgageHaircutPct = $percentages['mortgage_haircut_pct'] ?? null;
        $insurancePct = $percentages['insurance_pct'] ?? null;

        $distressValue = $distressPct !== null ? round($marketValue * $distressPct / 100, 2) : null;
        $forcedSaleValue = $forcedSalePct !== null ? round($marketValue * $forcedSalePct / 100, 2) : null;
        $mortgageValue = $mortgageHaircutPct !== null ? round($marketValue * (1 - $mortgageHaircutPct / 100), 2) : null;
        $insuranceValue = $insurancePct !== null ? round($marketValue * $insurancePct / 100, 2) : null;

        return compact('distressValue', 'forcedSaleValue', 'mortgageValue', 'insuranceValue');
    }
}
