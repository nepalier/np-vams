<?php

use App\Domain\Gis\Services\KmlExportService;
use App\Domain\Property\Models\LandParcel;
use App\Domain\Property\Models\Property;
use App\Models\Tenant;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->service = new KmlExportService;
    $this->property = Property::create(['tenant_id' => $this->tenant->id, 'property_name' => 'Test']);
});

test('exports valid, parseable XML', function () {
    $parcel = LandParcel::create([
        'tenant_id' => $this->tenant->id, 'property_id' => $this->property->id, 'kitta_number' => '123',
        'boundary_points' => [
            ['lat' => 27.70, 'lng' => 85.30], ['lat' => 27.70, 'lng' => 85.31], ['lat' => 27.71, 'lng' => 85.31],
        ],
    ]);

    $xml = $this->service->exportParcel($parcel);

    $dom = new DOMDocument;
    $loaded = $dom->loadXML($xml);

    expect($loaded)->toBeTrue();
    expect($xml)->toContain('<Polygon>');
    expect($xml)->toContain('<coordinates>');
});

test('a kitta number containing XML-special characters is correctly escaped, not left to corrupt the document', function () {
    $parcel = LandParcel::create([
        'tenant_id' => $this->tenant->id, 'property_id' => $this->property->id,
        'kitta_number' => 'Plot <A> & "B"', // deliberately hostile input
        'boundary_points' => [
            ['lat' => 27.70, 'lng' => 85.30], ['lat' => 27.70, 'lng' => 85.31], ['lat' => 27.71, 'lng' => 85.31],
        ],
    ]);

    $xml = $this->service->exportParcel($parcel);

    $dom = new DOMDocument;
    $loaded = $dom->loadXML($xml); // would fail to parse if the special characters weren't escaped

    expect($loaded)->toBeTrue();
});

test('a parcel with no boundary is rejected rather than producing an empty polygon', function () {
    $parcel = LandParcel::create(['tenant_id' => $this->tenant->id, 'property_id' => $this->property->id, 'kitta_number' => '456']);

    $this->service->exportParcel($parcel);
})->throws(InvalidArgumentException::class);
