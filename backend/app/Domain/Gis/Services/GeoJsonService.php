<?php

declare(strict_types=1);

namespace App\Domain\Gis\Services;

use App\Domain\Property\Models\LandParcel;
use App\Domain\Property\Models\Property;
use InvalidArgumentException;

/**
 * Section 19: "GeoJSON import and export." Uses the plain lat/lng
 * columns and boundary_points JSON already on Property/LandParcel (the
 * MySQL-portable replacement for PostGIS geometry columns) -- GeoJSON's
 * own coordinate order is [longitude, latitude], which is the opposite
 * of how this app's own JSON stores {lat, lng} pairs elsewhere, so the
 * conversion direction matters and is handled explicitly at each
 * boundary rather than assumed.
 */
class GeoJsonService
{
    public function exportProperty(Property $property): array
    {
        if ($property->latitude === null || $property->longitude === null) {
            throw new InvalidArgumentException('Property has no recorded coordinates to export.');
        }

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $property->longitude, (float) $property->latitude],
            ],
            'properties' => [
                'id' => $property->id,
                'property_code' => $property->property_code,
                'property_name' => $property->property_name,
            ],
        ];
    }

    public function exportParcel(LandParcel $parcel): array
    {
        if (empty($parcel->boundary_points) || count($parcel->boundary_points) < 3) {
            throw new InvalidArgumentException('Parcel has no recorded boundary polygon to export.');
        }

        // GeoJSON polygons must be explicitly closed (first coordinate
        // repeated as the last) -- boundary_points does not require this
        // internally, so it's enforced here at the export boundary.
        $coordinates = array_map(fn (array $p) => [(float) $p['lng'], (float) $p['lat']], $parcel->boundary_points);

        if ($coordinates[0] !== end($coordinates)) {
            $coordinates[] = $coordinates[0];
        }

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [$coordinates],
            ],
            'properties' => [
                'id' => $parcel->id,
                'kitta_number' => $parcel->kitta_number,
                'area_considered_sqm' => $parcel->area_considered_sqm,
            ],
        ];
    }

    /** @param  array<int, LandParcel>  $parcels */
    public function exportParcelCollection(iterable $parcels): array
    {
        $features = [];

        foreach ($parcels as $parcel) {
            try {
                $features[] = $this->exportParcel($parcel);
            } catch (InvalidArgumentException) {
                continue; // skip parcels with no boundary recorded, rather than fail the whole collection export
            }
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * @param  array  $geoJsonFeature  a single GeoJSON Feature with Polygon geometry
     * @return array<int, array{lat: float, lng: float}>  ready to assign directly to LandParcel::boundary_points
     */
    public function importParcelBoundary(array $geoJsonFeature): array
    {
        $geometry = $geoJsonFeature['geometry'] ?? throw new InvalidArgumentException('Feature has no geometry.');

        if (($geometry['type'] ?? null) !== 'Polygon') {
            throw new InvalidArgumentException("Expected a Polygon geometry, got: {$geometry['type']}");
        }

        $ring = $geometry['coordinates'][0] ?? throw new InvalidArgumentException('Polygon has no coordinate ring.');

        $points = array_map(fn (array $coord) => ['lng' => (float) $coord[0], 'lat' => (float) $coord[1]], $ring);

        // Drop the closing duplicate vertex GeoJSON requires but this
        // app's own boundary_points format does not -- keeps the stored
        // representation consistent regardless of import source.
        if (count($points) > 1 && $points[0] === end($points)) {
            array_pop($points);
        }

        if (count($points) < 3) {
            throw new InvalidArgumentException('A polygon needs at least 3 distinct vertices.');
        }

        return $points;
    }
}
