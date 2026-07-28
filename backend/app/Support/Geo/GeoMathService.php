<?php

declare(strict_types=1);

namespace App\Support\Geo;

use InvalidArgumentException;

/**
 * The application-level replacement promised when this project moved from
 * PostGIS to plain lat/lng decimals for MySQL/shared-hosting portability
 * (see the property_and_parcel migration's docblock: "Distance/area
 * calculations that would have used PostGIS spatial functions now need
 * application-level math"). This is that follow-up.
 *
 * Distance: standard haversine formula (great-circle distance on a
 * sphere) -- accurate to well within survey tolerance at the scale a
 * single property/comparable-property distance is ever measured at.
 *
 * Area: an equirectangular (locally flat) projection of the polygon's
 * lat/lng vertices into metres relative to the polygon's own first
 * vertex, then the standard shoelace formula. This is the same
 * practical approximation commonly used for parcel-sized areas (a few
 * hundred to a few thousand square metres) -- it would NOT be
 * appropriate for country-scale polygons, but that's never the use case
 * here (a land parcel boundary).
 */
class GeoMathService
{
    private const EARTH_RADIUS_METERS = 6_371_000.0;

    public function haversineDistanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $points  polygon vertices in order (need not be closed --
     *         the last point is not required to repeat the first)
     */
    public function polygonAreaSquareMeters(array $points): float
    {
        if (count($points) < 3) {
            throw new InvalidArgumentException('A polygon needs at least 3 vertices to have an area.');
        }

        $referenceLat = $points[0]['lat'];
        $referenceLng = $points[0]['lng'];

        $projected = array_map(
            fn (array $p) => $this->projectToLocalMeters($p['lat'], $p['lng'], $referenceLat, $referenceLng),
            $points,
        );

        $area = 0.0;
        $count = count($projected);

        for ($i = 0; $i < $count; $i++) {
            $j = ($i + 1) % $count;
            $area += $projected[$i]['x'] * $projected[$j]['y'];
            $area -= $projected[$j]['x'] * $projected[$i]['y'];
        }

        return abs($area) / 2;
    }

    /** @return array{x: float, y: float} */
    private function projectToLocalMeters(float $lat, float $lng, float $referenceLat, float $referenceLng): array
    {
        $x = deg2rad($lng - $referenceLng) * self::EARTH_RADIUS_METERS * cos(deg2rad($referenceLat));
        $y = deg2rad($lat - $referenceLat) * self::EARTH_RADIUS_METERS;

        return ['x' => $x, 'y' => $y];
    }

    /**
     * Convenience wrapper for the common case of "how far is this
     * comparable property from the subject property" -- both
     * Property and ComparableProperty expose latitude/longitude the same
     * way, so this accepts any object with those two properties rather
     * than importing both domain models into a support-layer class.
     */
    public function distanceBetweenPoints(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return null;
        }

        return round($this->haversineDistanceMeters($lat1, $lng1, $lat2, $lng2), 2);
    }
}
