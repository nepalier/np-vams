<?php

declare(strict_types=1);

namespace App\Domain\Property\Services;

use App\Domain\Property\Models\LandParcel;

/**
 * The actual point of building LandParcelCharacteristics/Planning
 * (Sections 11 & 12): converting recorded field data into the
 * MarketComparisonEngine's adjustment-factor input shape (Section 22 --
 * "Adjustment factors shall include: ... road width, access, plot size,
 * shape, frontage, ... topography, flood risk, landslide risk ...").
 *
 * Every factor here is a MULTIPLIER (1.00 = no adjustment) derived from
 * the subject property's own recorded characteristics relative to a
 * neutral baseline -- these are SUGGESTED starting factors for the
 * valuer to review and override, never applied silently. The specific
 * multiplier magnitudes (e.g. "steep slope = -8%") are intentionally
 * configurable constructor arguments rather than hard-coded literals
 * buried in the match arms, per Section 49's "do not hard-code Nepal-
 * specific valuation percentages."
 */
class LandAdjustmentFactorMapper
{
    public function __construct(
        private readonly array $topographyAdjustments = [
            'flat' => 1.00, 'gentle_slope' => 0.97, 'steep_slope' => 0.90, 'undulating' => 0.93,
        ],
        private readonly array $floodExposureAdjustments = [
            'none' => 1.00, 'low' => 0.95, 'moderate' => 0.85, 'high' => 0.70,
        ],
        private readonly array $landslideExposureAdjustments = [
            'none' => 1.00, 'low' => 0.95, 'moderate' => 0.85, 'high' => 0.70,
        ],
        private readonly array $accessAdjustments = [
            'motorable' => 1.00, 'foot_trail' => 0.85, 'no_direct_access' => 0.65,
        ],
        private readonly float $cornerPlotPremium = 1.05,
        private readonly float $roadWidthReferenceMetres = 6.0,
        private readonly float $roadWidthAdjustmentPerMetre = 0.01, // ±1% per metre difference from reference
    ) {}

    /**
     * @return array<string, float>  ready to merge into a
     *         MarketComparisonEngine comparable's `factors` array
     */
    public function map(LandParcel $parcel): array
    {
        $characteristics = $parcel->characteristics;
        $factors = [];

        if ($characteristics === null) {
            return $factors; // no recorded characteristics -- no factors suggested, not a fabricated 1.00 for everything
        }

        if ($characteristics->topography !== null) {
            $factors['topography'] = $this->topographyAdjustments[$characteristics->topography] ?? 1.00;
        }

        if ($characteristics->flood_exposure !== null) {
            $factors['flood_risk'] = $this->floodExposureAdjustments[$characteristics->flood_exposure] ?? 1.00;
        }

        if ($characteristics->landslide_exposure !== null) {
            $factors['landslide_risk'] = $this->landslideExposureAdjustments[$characteristics->landslide_exposure] ?? 1.00;
        }

        if ($characteristics->access_type !== null) {
            $factors['access'] = $this->accessAdjustments[$characteristics->access_type] ?? 1.00;
        }

        if ($characteristics->is_corner_plot) {
            $factors['corner_plot'] = $this->cornerPlotPremium;
        }

        if ($characteristics->road_width_m !== null) {
            $diff = (float) $characteristics->road_width_m - $this->roadWidthReferenceMetres;
            $factors['road_width'] = round(1.00 + ($diff * $this->roadWidthAdjustmentPerMetre), 4);
        }

        return $factors;
    }
}
