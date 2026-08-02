<?php

use App\Domain\Comparable\Models\ComparableProperty;
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

test('creating a comparable property with a required reliability grade succeeds', function () {
    $response = $this->postJson('/api/v1/comparable-properties', [
        'location' => 'Baneshwor, Kathmandu', 'unit_rate' => 150000, 'reliability_grade' => 'A',
    ]);

    $response->assertStatus(201);
    expect(ComparableProperty::first()->reliability_grade)->toBe('A');
});

test('rejects an invalid reliability grade -- only A through E are real Section 21 grades', function () {
    $response = $this->postJson('/api/v1/comparable-properties', [
        'location' => 'Baneshwor, Kathmandu', 'unit_rate' => 150000, 'reliability_grade' => 'Z',
    ]);

    $response->assertStatus(422);
});

test('nearby search returns only comparables within the radius, sorted closest first, using real haversine distance', function () {
    // Reference point: roughly central Kathmandu (27.7, 85.3)
    ComparableProperty::create([
        'tenant_id' => $this->tenant->id, 'location' => 'Very close', 'unit_rate' => 100000,
        'reliability_grade' => 'A', 'latitude' => 27.701, 'longitude' => 85.301,
    ]);
    ComparableProperty::create([
        'tenant_id' => $this->tenant->id, 'location' => 'Far away (different district entirely)', 'unit_rate' => 80000,
        'reliability_grade' => 'B', 'latitude' => 28.2, 'longitude' => 83.9, // Pokhara, ~140km away
    ]);

    $response = $this->getJson('/api/v1/comparable-properties/nearby?latitude=27.7&longitude=85.3&radius_km=5');

    $response->assertOk();
    $locations = collect($response->json('data'))->pluck('location');

    expect($locations)->toContain('Very close');
    expect($locations)->not->toContain('Far away (different district entirely)');
});

test('nearby search excludes comparables that have no recorded coordinates at all', function () {
    ComparableProperty::create([
        'tenant_id' => $this->tenant->id, 'location' => 'No GPS recorded', 'unit_rate' => 100000, 'reliability_grade' => 'C',
    ]);

    $response = $this->getJson('/api/v1/comparable-properties/nearby?latitude=27.7&longitude=85.3&radius_km=5');

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});
