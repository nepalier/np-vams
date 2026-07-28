<?php

use App\Domain\Gis\Services\GeoJsonService;
use App\Domain\Property\Models\LandParcel;
use App\Domain\Property\Models\Property;
use App\Models\Tenant;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->service = new GeoJsonService;
});

test('exporting a property produces a valid GeoJSON Point with coordinates in [lng, lat] order', function () {
    $property = Property::create([
        'tenant_id' => $this->tenant->id, 'property_name' => 'Test', 'latitude' => 27.7172, 'longitude' => 85.3240,
    ]);

    $geoJson = $this->service->exportProperty($property);

    expect($geoJson['geometry']['type'])->toBe('Point');
    // GeoJSON order is [longitude, latitude] -- the opposite of this app's
    // own {lat, lng} convention, so this specifically checks the order
    // wasn't accidentally reversed.
    expect($geoJson['geometry']['coordinates'])->toBe([85.324, 27.7172]);
});

test('exporting a property with no coordinates throws rather than emitting a fabricated Point at 0,0', function () {
    $property = Property::create(['tenant_id' => $this->tenant->id, 'property_name' => 'No coords']);

    $this->service->exportProperty($property);
})->throws(InvalidArgumentException::class);

test('exporting a parcel closes the polygon ring even though boundary_points itself is not closed', function () {
    $property = Property::create(['tenant_id' => $this->tenant->id, 'property_name' => 'Test']);
    $parcel = LandParcel::create([
        'tenant_id' => $this->tenant->id, 'property_id' => $property->id, 'kitta_number' => '1',
        'boundary_points' => [
            ['lat' => 27.70, 'lng' => 85.30],
            ['lat' => 27.70, 'lng' => 85.31],
            ['lat' => 27.71, 'lng' => 85.31],
        ],
    ]);

    $geoJson = $this->service->exportParcel($parcel);
    $ring = $geoJson['geometry']['coordinates'][0];

    expect($ring)->toHaveCount(4); // 3 original vertices + the closing repeat
    expect($ring[0])->toBe($ring[3]);
});

test('a GeoJSON polygon feature round-trips through import back to the same boundary_points shape', function () {
    $feature = [
        'type' => 'Feature',
        'geometry' => [
            'type' => 'Polygon',
            'coordinates' => [[
                [85.30, 27.70], [85.31, 27.70], [85.31, 27.71], [85.30, 27.70], // closed ring, GeoJSON [lng, lat] order
            ]],
        ],
    ];

    $points = $this->service->importParcelBoundary($feature);

    expect($points)->toHaveCount(3); // closing duplicate dropped
    expect($points[0])->toBe(['lng' => 85.30, 'lat' => 27.70]);
});

test('importing a non-Polygon geometry is rejected', function () {
    $this->service->importParcelBoundary([
        'type' => 'Feature',
        'geometry' => ['type' => 'Point', 'coordinates' => [85.3, 27.7]],
    ]);
})->throws(InvalidArgumentException::class);
