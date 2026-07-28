<?php

declare(strict_types=1);

namespace App\Domain\Property\Services;

use App\Domain\Property\Models\LandParcel;
use App\Support\Geo\GeoMathService;

/**
 * Section 31's "Area mismatch" automated validation rule, never actually
 * implemented despite being in the original validation-rules list --
 * closing it now that GeoMathService exists to compute a real polygon
 * area from boundary_points to compare against.
 */
class ParcelAreaConsistencyChecker
{
    public function __construct(
        private readonly GeoMathService $geoMath,
        /** Percentage difference beyond which this is flagged as a mismatch worth a valuer's attention. */
        private readonly float $toleranceThresholdPct = 10.0,
    ) {}

    /**
     * @return array{
     *   has_boundary_polygon: bool,
     *   polygon_derived_area_sqm: float|null,
     *   recorded_area_sqm: float|null,
     *   difference_pct: float|null,
     *   is_mismatch: bool,
     *   severity: string,
     * }
     */
    public function check(LandParcel $parcel): array
    {
        $boundaryPoints = $parcel->boundary_points;
        $recordedArea = $parcel->area_considered_sqm ?? $parcel->area_site_measured_sqm ?? $parcel->area_lalpurja_sqm;

        if (empty($boundaryPoints) || count($boundaryPoints) < 3) {
            return [
                'has_boundary_polygon' => false,
                'polygon_derived_area_sqm' => null,
                'recorded_area_sqm' => $recordedArea !== null ? (float) $recordedArea : null,
                'difference_pct' => null,
                'is_mismatch' => false,
                'severity' => 'information',
            ];
        }

        $polygonArea = round($this->geoMath->polygonAreaSquareMeters($boundaryPoints), 2);

        if ($recordedArea === null) {
            return [
                'has_boundary_polygon' => true,
                'polygon_derived_area_sqm' => $polygonArea,
                'recorded_area_sqm' => null,
                'difference_pct' => null,
                'is_mismatch' => false,
                'severity' => 'information',
            ];
        }

        $recordedArea = (float) $recordedArea;
        $differencePct = $recordedArea > 0
            ? round((abs($polygonArea - $recordedArea) / $recordedArea) * 100, 2)
            : 100.0;

        $isMismatch = $differencePct > $this->toleranceThresholdPct;

        return [
            'has_boundary_polygon' => true,
            'polygon_derived_area_sqm' => $polygonArea,
            'recorded_area_sqm' => $recordedArea,
            'difference_pct' => $differencePct,
            'is_mismatch' => $isMismatch,
            // Section 31 severity levels: information|warning|high_risk|blocking_error.
            // A mismatch here is a data-quality flag for review, not grounds
            // to block the workflow outright -- warning, not blocking_error.
            'severity' => $isMismatch ? 'warning' : 'information',
        ];
    }
}
