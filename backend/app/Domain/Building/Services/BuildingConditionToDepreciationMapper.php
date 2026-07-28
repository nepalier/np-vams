<?php

declare(strict_types=1);

namespace App\Domain\Building\Services;

use App\Domain\Building\Models\BuildingConditionAssessment;
use InvalidArgumentException;

/**
 * The actual point of building the condition-assessment checklist
 * (Section 15): converting per-item ratings into the
 * CostApproachEngine's 'observed_condition' depreciation method inputs
 * (Section 23), so a valuer choosing that method has a real recorded
 * basis for the percentage they enter rather than typing in a number
 * with nothing behind it.
 *
 * Two separate outputs, matching the engine's own separation of concerns:
 *  - physical_depreciation_pct: derived from the STRUCTURAL items
 *    (foundation, columns, beams, slabs, walls, cracks, settlement,
 *    dampness, roof) -- physical wear and tear.
 *  - functional/economic obsolescence are reported back as their own
 *    average ratings (1-5), NOT converted to a currency amount here,
 *    since that conversion needs the replacement-cost-new figure the
 *    mapper doesn't have -- the caller multiplies against RCN itself.
 */
class BuildingConditionToDepreciationMapper
{
    private const STRUCTURAL_ITEM_TYPES = [
        'foundation', 'columns', 'beams', 'slabs', 'walls', 'cracks', 'settlement', 'dampness', 'roof',
    ];

    public function __construct(
        /** rating (1..5) => suggested physical depreciation percentage */
        private readonly array $ratingToDepreciationPct = [
            1 => 5.0, 2 => 15.0, 3 => 30.0, 4 => 50.0, 5 => 75.0,
        ],
    ) {}

    /**
     * @return array{
     *   physical_depreciation_pct: float|null,
     *   functional_obsolescence_rating: float|null,
     *   economic_obsolescence_rating: float|null,
     *   items_considered: int,
     * }
     */
    public function map(BuildingConditionAssessment $assessment): array
    {
        $items = $assessment->items;

        $structuralRatings = $items
            ->whereIn('item_type', self::STRUCTURAL_ITEM_TYPES)
            ->pluck('condition_rating');

        $functionalRating = $items->firstWhere('item_type', 'functional_obsolescence')?->condition_rating;
        $economicRating = $items->firstWhere('item_type', 'economic_obsolescence')?->condition_rating;

        $physicalDepreciationPct = null;

        if ($structuralRatings->isNotEmpty()) {
            $averageRating = (float) $structuralRatings->avg();
            $physicalDepreciationPct = $this->interpolate($averageRating);
        } elseif ($assessment->overall_rating !== null) {
            // Fall back to the assessment's single overall_rating if no
            // per-item structural ratings were recorded -- still a real
            // basis, just coarser.
            $physicalDepreciationPct = $this->interpolate((float) $assessment->overall_rating);
        }

        return [
            'physical_depreciation_pct' => $physicalDepreciationPct,
            'functional_obsolescence_rating' => $functionalRating !== null ? (float) $functionalRating : null,
            'economic_obsolescence_rating' => $economicRating !== null ? (float) $economicRating : null,
            'items_considered' => $structuralRatings->count(),
        ];
    }

    /** Linear interpolation between the configured integer rating points -- handles a non-integer average rating gracefully. */
    private function interpolate(float $rating): float
    {
        $ratings = array_keys($this->ratingToDepreciationPct);
        $min = min($ratings);
        $max = max($ratings);

        if ($rating <= $min) {
            return $this->ratingToDepreciationPct[$min];
        }

        if ($rating >= $max) {
            return $this->ratingToDepreciationPct[$max];
        }

        $lower = (int) floor($rating);
        $upper = (int) ceil($rating);

        if ($lower === $upper) {
            return $this->ratingToDepreciationPct[$lower]
                ?? throw new InvalidArgumentException("No depreciation percentage configured for rating {$lower}.");
        }

        $lowerPct = $this->ratingToDepreciationPct[$lower];
        $upperPct = $this->ratingToDepreciationPct[$upper];
        $fraction = $rating - $lower;

        return round($lowerPct + ($upperPct - $lowerPct) * $fraction, 2);
    }
}
