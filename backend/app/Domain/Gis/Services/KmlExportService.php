<?php

declare(strict_types=1);

namespace App\Domain\Gis\Services;

use App\Domain\Property\Models\LandParcel;
use InvalidArgumentException;

/**
 * Section 19: "KML ... export." KML coordinates are lng,lat[,altitude]
 * comma-separated, space-separated between vertices -- built with PHP's
 * DOMDocument for correct XML escaping (a kitta number or remark
 * containing '&' or '<' must not corrupt the file), not raw string
 * concatenation.
 *
 * KML IMPORT is intentionally not implemented here: real-world KML has
 * enough dialect variation (Google Earth vs. QGIS vs. government GIS
 * tools) that a naive parser would silently mis-import edge cases rather
 * than fail loudly, which is worse than not supporting it -- flagged as
 * a follow-on rather than shipped half-correct.
 */
class KmlExportService
{
    public function exportParcel(LandParcel $parcel): string
    {
        if (empty($parcel->boundary_points) || count($parcel->boundary_points) < 3) {
            throw new InvalidArgumentException('Parcel has no recorded boundary polygon to export.');
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $kml = $dom->createElementNS('http://www.opengis.net/kml/2.2', 'kml');
        $dom->appendChild($kml);

        $document = $dom->createElement('Document');
        $kml->appendChild($document);

        $placemark = $dom->createElement('Placemark');
        $document->appendChild($placemark);

        $name = $dom->createElement('name', htmlspecialchars('Parcel '.($parcel->kitta_number ?? $parcel->id)));
        $placemark->appendChild($name);

        if ($parcel->area_considered_sqm !== null) {
            $description = $dom->createElement('description', htmlspecialchars("Area: {$parcel->area_considered_sqm} sqm"));
            $placemark->appendChild($description);
        }

        $polygon = $dom->createElement('Polygon');
        $placemark->appendChild($polygon);

        $outerBoundary = $dom->createElement('outerBoundaryIs');
        $polygon->appendChild($outerBoundary);

        $linearRing = $dom->createElement('LinearRing');
        $outerBoundary->appendChild($linearRing);

        $coordinateStrings = array_map(
            fn (array $p) => sprintf('%F,%F,0', (float) $p['lng'], (float) $p['lat']),
            $parcel->boundary_points,
        );

        // KML polygons must be closed the same way GeoJSON's are.
        if ($coordinateStrings[0] !== end($coordinateStrings)) {
            $coordinateStrings[] = $coordinateStrings[0];
        }

        $coordinates = $dom->createElement('coordinates', implode(' ', $coordinateStrings));
        $linearRing->appendChild($coordinates);

        return $dom->saveXML();
    }
}
