<?php

use App\Domain\Property\Models\LandParcel;
use App\Domain\Property\Models\LandParcelCharacteristics;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Services\LandAdjustmentFactorMapper;
use App\Models\Tenant;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->property = Property::create(['tenant_id' => $this->tenant->id, 'property_name' => 'Test Property']);

    $this->parcel = LandParcel::create([
        'tenant_id' => $this->tenant->id,
        'property_id' => $this->property->id,
        'kitta_number' => '123',
    ]);

    $this->mapper = new LandAdjustmentFactorMapper;
});

test('returns no factors when the parcel has no recorded characteristics, rather than fabricating neutral ones', function () {
    $factors = $this->mapper->map($this->parcel->fresh());

    expect($factors)->toBe([]);
});

test('steep slope and high flood exposure produce discount factors below 1.0', function () {
    LandParcelCharacteristics::create([
        'tenant_id' => $this->tenant->id,
        'land_parcel_id' => $this->parcel->id,
        'topography' => 'steep_slope',
        'flood_exposure' => 'high',
    ]);

    $factors = $this->mapper->map($this->parcel->fresh()->load('characteristics'));

    expect($factors['topography'])->toBeLessThan(1.0);
    expect($factors['flood_risk'])->toBeLessThan(1.0);
});

test('a corner plot receives a premium factor above 1.0', function () {
    LandParcelCharacteristics::create([
        'tenant_id' => $this->tenant->id,
        'land_parcel_id' => $this->parcel->id,
        'is_corner_plot' => true,
    ]);

    $factors = $this->mapper->map($this->parcel->fresh()->load('characteristics'));

    expect($factors['corner_plot'])->toBeGreaterThan(1.0);
});

test('road width wider than the reference produces a premium, narrower produces a discount', function () {
    LandParcelCharacteristics::create([
        'tenant_id' => $this->tenant->id, 'land_parcel_id' => $this->parcel->id, 'road_width_m' => 10.0,
    ]);
    $wide = $this->mapper->map($this->parcel->fresh()->load('characteristics'));

    $narrowParcel = LandParcel::create(['tenant_id' => $this->tenant->id, 'property_id' => $this->property->id, 'kitta_number' => '456']);
    LandParcelCharacteristics::create([
        'tenant_id' => $this->tenant->id, 'land_parcel_id' => $narrowParcel->id, 'road_width_m' => 3.0,
    ]);
    $narrow = $this->mapper->map($narrowParcel->fresh()->load('characteristics'));

    expect($wide['road_width'])->toBeGreaterThan(1.0);
    expect($narrow['road_width'])->toBeLessThan(1.0);
});

test('the mapped factors feed directly into MarketComparisonEngine without modification', function () {
    LandParcelCharacteristics::create([
        'tenant_id' => $this->tenant->id, 'land_parcel_id' => $this->parcel->id,
        'is_corner_plot' => true, 'topography' => 'flat',
    ]);

    $factors = $this->mapper->map($this->parcel->fresh()->load('characteristics'));

    $result = app(\App\Domain\Valuation\Services\MarketComparisonEngine::class)->calculate([
        ['base_rate' => 100000, 'factors' => $factors],
    ]);

    // flat=1.00, corner=1.05 -> 100000 * 1.00 * 1.05 = 105000
    expect($result['per_comparable'][0]['adjusted_rate'])->toBe(105000.0);
});
