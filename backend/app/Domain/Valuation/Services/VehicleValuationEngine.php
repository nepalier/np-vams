<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Services;

use InvalidArgumentException;

/**
 * Matches the "Used Vehicle Valuation Report" / "Machinery-Equipment
 * Valuation Report" bank formats verbatim (identical line structure and
 * arithmetic across the vehicle and plant-and-machinery reference
 * documents used as the source for this engine):
 *
 *   1. Current Market Price of New Vehicle/Machinery
 *   2. Deduction @flat% as Scrap
 *   3. Bankable Value = 1 - 2
 *   4. Depreciation @%/annum on Bankable Value (line/straight basis) x age
 *   5. Net Value = 3 - 4
 *   6. Other Reducing Factors
 *   7. Other Costs @%/annum
 *   8. Net Fair Market Value = 5 - 6 - 7
 *
 * This is a genuinely new asset class for this system -- the existing
 * engines (market comparison, cost approach, income approach, residual)
 * were all built for land/building; vehicle and plant-and-machinery
 * valuation follow a completely different, simpler depreciation-from-
 * replacement-cost formula that none of them capture. Every percentage
 * is constructor-injected, matching the "10% flat scrap, 10%/annum
 * depreciation, 2%/annum other costs" figures the source documents state
 * as their convention -- never hard-coded, since a different bank's
 * guideline can specify different figures.
 */
class VehicleValuationEngine
{
    public function __construct(
        private readonly float $scrapDeductionPct = 10.0,
        private readonly float $depreciationPctPerAnnum = 10.0,
        private readonly float $otherCostPctPerAnnum = 2.0,
    ) {}

    /**
     * @param  float  $currentMarketPriceOfNew
     * @param  float  $ageYears
     * @param  float  $otherReducingFactors  a direct currency deduction (accident history, missing parts, etc.), not a percentage
     */
    public function calculate(
        float $currentMarketPriceOfNew,
        float $ageYears,
        float $otherReducingFactors = 0.0,
    ): array {
        if ($currentMarketPriceOfNew < 0) {
            throw new InvalidArgumentException('currentMarketPriceOfNew must be non-negative.');
        }

        if ($ageYears < 0) {
            throw new InvalidArgumentException('ageYears must be non-negative.');
        }

        if ($otherReducingFactors < 0) {
            throw new InvalidArgumentException('otherReducingFactors must be non-negative -- it is subtracted, not added.');
        }

        $scrapDeduction = round($currentMarketPriceOfNew * $this->scrapDeductionPct / 100, 2);
        $bankableValue = round($currentMarketPriceOfNew - $scrapDeduction, 2);

        // "Depreciation is calculated on Line Basis" (straight-line) per
        // the source documents -- depreciationPctPerAnnum x age, not
        // compounded year-over-year.
        $depreciationAmount = round($bankableValue * $this->depreciationPctPerAnnum / 100 * $ageYears, 2);
        $netValue = max(0.0, round($bankableValue - $depreciationAmount, 2));

        $otherCostAmount = round($netValue * $this->otherCostPctPerAnnum / 100 * $ageYears, 2);

        $netFairMarketValue = max(0.0, round($netValue - $otherReducingFactors - $otherCostAmount, 2));

        return [
            'current_market_price_of_new' => $currentMarketPriceOfNew,
            'scrap_deduction_pct' => $this->scrapDeductionPct,
            'scrap_deduction_amount' => $scrapDeduction,
            'bankable_value' => $bankableValue,
            'depreciation_pct_per_annum' => $this->depreciationPctPerAnnum,
            'age_years' => $ageYears,
            'depreciation_amount' => $depreciationAmount,
            'net_value' => $netValue,
            'other_reducing_factors' => $otherReducingFactors,
            'other_cost_pct_per_annum' => $this->otherCostPctPerAnnum,
            'other_cost_amount' => $otherCostAmount,
            'net_fair_market_value' => $netFairMarketValue,
        ];
    }
}
