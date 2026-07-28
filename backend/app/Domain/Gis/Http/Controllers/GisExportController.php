<?php

declare(strict_types=1);

namespace App\Domain\Gis\Http\Controllers;

use App\Domain\Gis\Services\GeoJsonService;
use App\Domain\Gis\Services\KmlExportService;
use App\Domain\Property\Models\LandParcel;
use App\Domain\Property\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class GisExportController
{
    public function __construct(
        private readonly GeoJsonService $geoJson,
        private readonly KmlExportService $kml,
    ) {}

    public function exportPropertyGeoJson(Property $property): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        try {
            return response()->json($this->geoJson->exportProperty($property));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function exportParcelGeoJson(LandParcel $parcel): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        try {
            return response()->json($this->geoJson->exportParcel($parcel));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function exportParcelKml(LandParcel $parcel): Response
    {
        request()->user()->can('assignments.view') || abort(403);

        try {
            $xml = $this->kml->exportParcel($parcel);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.google-earth.kml+xml',
            'Content-Disposition' => "attachment; filename=\"parcel-{$parcel->kitta_number}.kml\"",
        ]);
    }

    public function importParcelBoundary(Request $request, LandParcel $parcel): JsonResponse
    {
        $request->user()->can('assignments.update') || abort(403);
        $request->validate(['geojson' => ['required', 'array']]);

        try {
            $boundaryPoints = $this->geoJson->importParcelBoundary($request->input('geojson'));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }

        $parcel->forceFill(['boundary_points' => $boundaryPoints])->save();

        return response()->json(['data' => $parcel->fresh()]);
    }

    private function error(string $message): JsonResponse
    {
        return response()->json([
            'errors' => [['status' => '422', 'title' => 'GisExportError', 'detail' => $message]],
        ], 422);
    }
}
