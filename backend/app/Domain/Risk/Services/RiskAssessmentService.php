<?php

declare(strict_types=1);

namespace App\Domain\Risk\Services;

use InvalidArgumentException;

/**
 * Section 29: configurable indicator-driven risk scoring with a mandatory-
 * justification professional override. Both the indicator weights and the
 * score-to-category bands are supplied by the caller (sourced from the
 * tenant's risk_indicators / risk_score_bands master data), never
 * hard-coded in this class.
 */
class RiskAssessmentService
{
    /**
     * @param  array<int, array{code: string, weight: float}>  $presentIndicators  indicators that apply to this property
     * @param  array<int, array{min_score: float, max_score: float, category: string}>  $scoreBands
     */
    public function assess(array $presentIndicators, array $scoreBands): array
    {
        $score = round(array_sum(array_column($presentIndicators, 'weight')), 2);

        $category = $this->resolveCategory($score, $scoreBands);

        return [
            'indicators_applied' => $presentIndicators,
            'computed_score' => $score,
            'computed_category' => $category,
        ];
    }

    private function resolveCategory(float $score, array $scoreBands): string
    {
        foreach ($scoreBands as $band) {
            if ($score >= $band['min_score'] && $score <= $band['max_score']) {
                return $band['category'];
            }
        }

        throw new InvalidArgumentException("No configured risk_score_band covers a score of {$score}. Check the tenant's risk_score_bands configuration for gaps.");
    }

    /**
     * Applies a professional override on top of a computed assessment.
     * Mirrors ReconciliationService's override pattern: allowed, but never
     * silent (Section 29: "allow professional override with justification").
     */
    public function applyOverride(string $computedCategory, ?string $overrideCategory, ?string $justification): array
    {
        if ($overrideCategory === null || $overrideCategory === $computedCategory) {
            return ['final_category' => $computedCategory, 'is_overridden' => false, 'override_justification' => null];
        }

        if (empty($justification)) {
            throw new InvalidArgumentException('A justification is required when overriding the computed risk category.');
        }

        return ['final_category' => $overrideCategory, 'is_overridden' => true, 'override_justification' => $justification];
    }
}
