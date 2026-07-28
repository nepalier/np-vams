<?php

use App\Domain\Property\Models\LandParcel;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Services\ParcelAreaConsistencyChecker;
use App\Models\Tenant;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->property = Property::create(['tenant_id' => $this->tenant->id, 'property_name' => 'Test Property']);
    $this->checker = app(ParcelAreaConsistencyChecker::class);
});

test('a parcel with no boundary polygon returns has_boundary_polygon false, not an error', function () {
    $parcel = LandParcel::create([
        'tenant_id' => $this->tenant->id, 'property_id' => $this->property->id,
        'kitta_number' => '1', 'area_considered_sqm' => 500,
    ]);

    $result = $this->checker->check($parcel);

    expect($result['has_boundary_polygon'])->toBeFalse();
    expect($result['is_mismatch'])->toBeFalse();
});

test('a polygon-derived area close to the recorded area is not flagged as a mismatch', function () {
    // Build a boundary polygon whose real area is very close to 10,000 sqm.
    $refLat = 27.70;
    $refLng = 85.30;
    $latOffset = rad2deg(100 / 6_371_000);
    $lngOffset = rad2deg(100 / (6_371_000 * cos(deg2rad($refLat))));

    $parcel = LandParcel::create([
        'tenant_id' => $this->tenant->id, 'property_id' => $this->property->id, 'kitta_number' => '2',
        'area_considered_sqm' => 10000,
        'boundary_points' => [
            ['lat' => $refLat, 'lng' => $refLng],
            ['lat' => $refLat, 'lng' => $refLng + $lngOffset],
            ['lat' => $refLat + $latOffset, 'lng' => $refLng + $lngOffset],
            ['lat' => $refLat + $latOffset, 'lng' => $refLng],
        ],
    ]);

    $result = $this->checker->check($parcel);

    expect($result['is_mismatch'])->toBeFalse();
    expect($result['severity'])->toBe('information');
});

test('a polygon-derived area far from the recorded area IS flagged as a mismatch warning', function () {
    $refLat = 27.70;
    $refLng = 85.30;
    $latOffset = rad2deg(100 / 6_371_000);
    $lngOffset = rad2deg(100 / (6_371_000 * cos(deg2rad($refLat))));

    $parcel = LandParcel::create([
        'tenant_id' => $this->tenant->id, 'property_id' => $this->property->id, 'kitta_number' => '3',
        'area_considered_sqm' => 50000, // way off from the ~10,000 sqm polygon
        'boundary_points' => [
            ['lat' => $refLat, 'lng' => $refLng],
            ['lat' => $refLat, 'lng' => $refLng + $lngOffset],
            ['lat' => $refLat + $latOffset, 'lng' => $refLng + $lngOffset],
            ['lat' => $refLat + $latOffset, 'lng' => $refLng],
        ],
    ]);

    $result = $this->checker->check($parcel);

    expect($result['is_mismatch'])->toBeTrue();
    expect($result['severity'])->toBe('warning');
    expect($result['difference_pct'])->toBeGreaterThan(10.0);
});
