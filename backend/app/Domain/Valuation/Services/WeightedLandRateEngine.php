<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Services;

use InvalidArgumentException;

/**
 * The de facto standard Nepali bank land-rate formula, found verbatim
 * (identical wording and arithmetic) across the Jyoti Bikash Bank, Sanima,
 * Muktinath Bikash Bank, and Prabhu Bank valuation report formats used as
 * the source reference for this engine:
 *
 *   Weighted Rate = (Government Rate × Government Weight%) + (Market Rate × Market Weight%)
 *
 * Government weight defaults to 30% and market weight to 70% -- the
 * convention stated explicitly in every one of those reference documents
 * ("Weighted value of land is 30% of Government rate and 70% of
 * Prevailing market rate") -- but both are constructor-injected, never
 * hard-coded literals in the calculation itself, since a specific bank's
 * guideline can and does specify a different split (Section 49: "do not
 * hard-code Nepal-specific valuation percentages").
 *
 * Verified against the JBBL reference document's own worked numbers:
 * Govt rate 2,300,000 & Market rate 6,500,000 -> weighted 5,240,000
 * (0.3 x 2,300,000 + 0.7 x 6,500,000 = 690,000 + 4,550,000 = 5,240,000),
 * matching the source document's stated result exactly -- see
 * WeightedLandRateEngineTest.
 */
class WeightedLandRateEngine
{
    public function __construct(
        private readonly float $governmentWeightPct = 30.0,
        private readonly float $marketWeightPct = 70.0,
    ) {
        if (round($governmentWeightPct + $marketWeightPct, 4) !== 100.0) {
            throw new InvalidArgumentException('Government and market weight percentages must sum to 100.');
        }
    }

    /**
     * @param  array<int, array{plot_label: string, area_considered: float, government_rate: float, market_rate: float}>  $plots
     *         one row per plot/kitta being valued, matching the source documents' per-plot weighted-rate tables
     */
    public function calculate(array $plots): array
    {
        if (count($plots) === 0) {
            throw new InvalidArgumentException('At least one plot is required.');
        }

        $rows = [];
        $totalValue = 0.0;

        foreach ($plots as $plot) {
            if ($plot['government_rate'] < 0 || $plot['market_rate'] < 0 || $plot['area_considered'] < 0) {
                throw new InvalidArgumentException('Rates and area must be non-negative.');
            }

            $governmentComponent = round($plot['government_rate'] * $this->governmentWeightPct / 100, 2);
            $marketComponent = round($plot['market_rate'] * $this->marketWeightPct / 100, 2);
            $weightedRate = round($governmentComponent + $marketComponent, 2);
            $plotValue = round($weightedRate * $plot['area_considered'], 2);

            $rows[] = [
                'plot_label' => $plot['plot_label'],
                'area_considered' => $plot['area_considered'],
                'government_rate' => $plot['government_rate'],
                'market_rate' => $plot['market_rate'],
                'government_component' => $governmentComponent,
                'market_component' => $marketComponent,
                'weighted_rate' => $weightedRate,
                'plot_value' => $plotValue,
            ];

            $totalValue += $plotValue;
        }

        return [
            'government_weight_pct' => $this->governmentWeightPct,
            'market_weight_pct' => $this->marketWeightPct,
            'plots' => $rows,
            'total_land_value' => round($totalValue, 2),
        ];
    }
}
