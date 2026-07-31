<?php

use App\Domain\Party\Models\Borrower;
use App\Domain\Party\Models\PropertyOwner;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('Tenant Administrator');

    Sanctum::actingAs($this->user, [], 'web');
});

test('creating a borrower via the API persists it scoped to the caller\'s tenant', function () {
    $response = $this->postJson('/api/v1/borrowers', ['party_kind' => 'individual', 'name_en' => 'Ramesh Sharma']);

    $response->assertStatus(201);
    expect(Borrower::where('name_en', 'Ramesh Sharma')->first()->tenant_id)->toBe($this->tenant->id);
});

test('listing borrowers only returns the caller\'s own tenant\'s borrowers', function () {
    Borrower::create(['tenant_id' => $this->tenant->id, 'party_kind' => 'individual', 'name_en' => 'My Borrower']);

    $otherTenant = Tenant::factory()->create();
    Borrower::create(['tenant_id' => $otherTenant->id, 'party_kind' => 'individual', 'name_en' => 'Other Borrower']);

    $response = $this->getJson('/api/v1/borrowers');

    $names = collect($response->json('data'))->pluck('name_en');
    expect($names)->toContain('My Borrower');
    expect($names)->not->toContain('Other Borrower');
});

test('creating a property owner with an ownership percentage persists correctly', function () {
    $response = $this->postJson('/api/v1/property-owners', [
        'party_kind' => 'individual', 'name_en' => 'Sita Gurung', 'ownership_type' => 'single', 'ownership_percentage' => 100,
    ]);

    $response->assertStatus(201);
    expect((float) PropertyOwner::where('name_en', 'Sita Gurung')->first()->ownership_percentage)->toBe(100.0);
});

test('a new assignment can reference a real borrower_id created through this endpoint', function () {
    $borrowerResponse = $this->postJson('/api/v1/borrowers', ['party_kind' => 'individual', 'name_en' => 'Test Borrower']);
    $borrowerId = $borrowerResponse->json('data.id');

    expect($borrowerId)->not->toBeNull();
    expect(Borrower::find($borrowerId))->not->toBeNull();
});
