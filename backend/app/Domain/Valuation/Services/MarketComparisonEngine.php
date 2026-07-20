<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Services;

use InvalidArgumentException;

/**
 * Section 22: "Adjusted unit rate = Base comparable unit rate × combined
 * adjustment factors". Pure calculation service -- no DB access -- so the
 * arithmetic itself can be unit-tested without a database, and so a
 * controller/job can persist exactly what this returns into
 * valuation_calculations.computed_details without any silent recomputation
 * happening later.
 */
class MarketComparisonEngine
{
    /**
     * @param  array<int, array{base_rate: float, weight?: float, factors: array<string, float>}>  $comparables
     *         Each `factors` value is a MULTIPLIER (1.00 = no change, 1.05 = +5%, 0.95 = -5%).
     * @param  float  $outlierStdDevThreshold  comparables whose adjusted rate deviates from the
     *         mean by more than this many standard deviations are flagged, not silently dropped.
     */
    public function calculate(array $comparables, float $outlierStdDevThreshold = 2.0): array
    {
        if (count($comparables) === 0) {
            throw new InvalidArgumentException('At least one comparable is required.');
        }

        $adjustedRates = [];
        $weights = [];
        $breakdown = [];

        foreach ($comparables as $index => $comparable) {
            if ($comparable['base_rate'] < 0) {
                throw new InvalidArgumentException("Comparable #{$index}: base_rate cannot be negative.");
            }

            $combinedFactor = array_product(array_values($comparable['factors']));
            $adjustedRate = round($comparable['base_rate'] * $combinedFactor, 2);
            $weight = $comparable['weight'] ?? 1.0;

            $adjustedRates[] = $adjustedRate;
            $weights[] = $weight;

            $breakdown[] = [
                'index' => $index,
                'base_rate' => $comparable['base_rate'],
                'factors' => $comparable['factors'],
                'combined_factor' => round($combinedFactor, 6),
                'adjusted_rate' => $adjustedRate,
                'weight' => $weight,
            ];
        }

        $count = count($adjustedRates);
        $mean = array_sum($adjustedRates) / $count;
        $median = $this->median($adjustedRates);
        $weightedAverage = $this->weightedAverage($adjustedRates, $weights);
        $stdDev = $this->sampleStandardDeviation($adjustedRates, $mean);
        $min = min($adjustedRates);
        $max = max($adjustedRates);

        $outlierIndices = [];
        foreach ($adjustedRates as $index => $rate) {
            if ($stdDev > 0 && abs($rate - $mean) > $outlierStdDevThreshold * $stdDev) {
                $outlierIndices[] = $index;
            }
        }

        return [
            'per_comparable' => $breakdown,
            'mean' => round($mean, 2),
            'median' => round($median, 2),
            'weighted_average' => round($weightedAverage, 2),
            'min' => $min,
            'max' => $max,
            'standard_deviation' => round($stdDev, 2),
            'outlier_indices' => $outlierIndices,
            // Weighted average is the engine's SUGGESTED adopted rate; the
            // valuer may override it, but any override is recorded with
            // justification at the reconciliation stage, not silently here.
            'suggested_adopted_rate' => round($weightedAverage, 2),
        ];
    }

    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    private function weightedAverage(array $values, array $weights): float
    {
        $weightSum = array_sum($weights);

        if ($weightSum <= 0) {
            throw new InvalidArgumentException('Sum of comparable weights must be positive.');
        }

        $sum = 0.0;
        foreach ($values as $index => $value) {
            $sum += $value * $weights[$index];
        }

        return $sum / $weightSum;
    }

    private function sampleStandardDeviation(array $values, float $mean): float
    {
        $count = count($values);

        if ($count < 2) {
            return 0.0;
        }

        $sumSquaredDiffs = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values));

        return sqrt($sumSquaredDiffs / ($count - 1));
    }
}
