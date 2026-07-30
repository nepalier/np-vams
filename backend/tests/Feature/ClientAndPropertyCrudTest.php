<?php

use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\AreaUnit;
use App\Domain\Property\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('Tenant Administrator');

    Sanctum::actingAs($this->user, [], 'web');
});

test('creating a client via the API persists it scoped to the caller\'s tenant', function () {
    $response = $this->postJson('/api/v1/clients', [
        'name_en' => 'Everest Bank', 'client_type' => 'commercial_bank',
    ]);

    $response->assertStatus(201);
    expect(Client::where('name_en', 'Everest Bank')->first()->tenant_id)->toBe($this->tenant->id);
});

test('listing clients only returns the caller\'s own tenant\'s clients', function () {
    Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'My Bank', 'client_type' => 'commercial_bank']);

    $otherTenant = Tenant::factory()->create();
    Client::create(['tenant_id' => $otherTenant->id, 'name_en' => 'Other Bank', 'client_type' => 'commercial_bank']);

    $response = $this->getJson('/api/v1/clients');

    $response->assertStatus(200);
    $names = collect($response->json('data'))->pluck('name_en');
    expect($names)->toContain('My Bank');
    expect($names)->not->toContain('Other Bank');
});

test('creating a property auto-generates a sequential property_code', function () {
    $response = $this->postJson('/api/v1/properties', ['property_name' => 'Test Building']);

    $response->assertStatus(201);
    expect($response->json('data.property_code'))->toBe('PROP-000001');
});

test('creating a land parcel converts the entered area to square metres via the real Area value object', function () {
    $property = Property::create(['tenant_id' => $this->tenant->id, 'property_name' => 'Test']);
    $ropani = AreaUnit::where('code', 'ropani')->first();

    $response = $this->postJson("/api/v1/properties/{$property->id}/parcels", [
        'kitta_number' => '123',
        'area_lalpurja' => 2,
        'area_lalpurja_unit_id' => $ropani->id,
    ]);

    $response->assertStatus(201);
    // 2 Ropani * 508.72 sqm/ropani = 1017.44 sqm (matches the real conversion factor seeded in Phase 2)
    expect((float) $response->json('data.area_lalpurja_sqm'))->toBe(1017.44);
    // The originally entered value and unit are preserved exactly, not overwritten.
    expect((float) $response->json('data.area_lalpurja'))->toBe(2.0);
});
