<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Domain\Valuation\Services\ValuationCalculationService;
use App\Models\Tenant;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);
    $this->service = app(ValuationCalculationService::class);
});

function makeTenantAssignment(array $tenantAttributes = [], array $clientAttributes = []): ValuationAssignment
{
    $tenant = Tenant::factory()->create($tenantAttributes);
    app()->instance('currentTenantId', $tenant->id);

    $client = Client::create(array_merge([
        'tenant_id' => $tenant->id, 'name_en' => 'Test Bank', 'client_type' => 'commercial_bank',
    ], $clientAttributes));

    $fiscalYear = FiscalYear::where('is_current', true)->first();

    return ValuationAssignment::create([
        'tenant_id' => $tenant->id, 'assignment_number' => 'VAL-TEST-'.uniqid(),
        'fiscal_year_id' => $fiscalYear->id, 'client_id' => $client->id,
        'assignment_date' => now(), 'priority' => 'normal',
        'valuation_purpose_id' => ValuationPurpose::first()->id, 'status' => 'draft',
    ]);
}

test('a tenant-level land rate default is used when the client has no override of its own', function () {
    $assignment = makeTenantAssignment(tenantAttributes: [
        'default_land_rate_government_weight_pct' => 40, 'default_land_rate_market_weight_pct' => 60,
    ]);

    $calculation = $this->service->runWeightedLandRate(
        tenantId: $assignment->tenant_id, assignmentId: $assignment->id, propertyId: null,
        input: ['plots' => [['plot_label' => 'A', 'area_considered' => 1, 'government_rate' => 1_000_000, 'market_rate' => 2_000_000]]],
        calculatedByUserId: null,
    );

    // 40% x 1M + 60% x 2M = 400,000 + 1,200,000 = 1,600,000 (NOT the 30/70 engine default, which would give 1,700,000)
    expect($calculation->computed_details['plots'][0]['weighted_rate'])->toBe(1_600_000.0);
});

test('a client\'s own convention still beats the tenant-level default', function () {
    $assignment = makeTenantAssignment(
        tenantAttributes: ['default_land_rate_government_weight_pct' => 40, 'default_land_rate_market_weight_pct' => 60],
        clientAttributes: ['land_rate_government_weight_pct' => 20, 'land_rate_market_weight_pct' => 80],
    );

    $calculation = $this->service->runWeightedLandRate(
        tenantId: $assignment->tenant_id, assignmentId: $assignment->id, propertyId: null,
        input: ['plots' => [['plot_label' => 'A', 'area_considered' => 1, 'government_rate' => 1_000_000, 'market_rate' => 2_000_000]]],
        calculatedByUserId: null,
    );

    // Client's 20/80 wins over tenant's 40/60: 20% x 1M + 80% x 2M = 1,800,000
    expect($calculation->computed_details['plots'][0]['weighted_rate'])->toBe(1_800_000.0);
});

test('a tenant-level vehicle depreciation default is applied when no per-request override is given', function () {
    $assignment = makeTenantAssignment(tenantAttributes: [
        'default_vehicle_scrap_pct' => 5, 'default_vehicle_depreciation_pct_per_annum' => 5, 'default_vehicle_other_cost_pct_per_annum' => 1,
    ]);

    $calculation = $this->service->runVehicleValuation(
        tenantId: $assignment->tenant_id, assignmentId: $assignment->id,
        input: ['current_market_price_of_new' => 1_000_000, 'age_years' => 1],
        calculatedByUserId: null,
    );

    // 5% scrap (not the engine's 10% default) -> bankable 950,000
    expect($calculation->computed_details['scrap_deduction_amount'])->toBe(50_000.0);
});

test('a tenant-level building fixture default is applied when no per-request override is given', function () {
    $assignment = makeTenantAssignment(tenantAttributes: [
        'default_building_sanitary_fixture_pct' => 3, 'default_building_electrical_fixture_pct' => 4, 'default_building_depreciation_pct_per_annum' => 1,
    ]);

    $calculation = $this->service->runBuildingCostEstimation(
        tenantId: $assignment->tenant_id, assignmentId: $assignment->id, propertyId: null, buildingId: null,
        input: ['floors' => [['floor_name' => 'Ground', 'area' => 100, 'rate_per_unit_area' => 10000]], 'age_years' => 0],
        calculatedByUserId: null,
    );

    // 3% sanitary (not the engine's 5% default) on 1,000,000 civil works
    expect($calculation->computed_details['sanitary_cost'])->toBe(30_000.0);
});

test('an unconfigured tenant falls all the way through to each engine\'s own hard default', function () {
    $assignment = makeTenantAssignment(); // no tenant or client overrides at all

    $calculation = $this->service->runVehicleValuation(
        tenantId: $assignment->tenant_id, assignmentId: $assignment->id,
        input: ['current_market_price_of_new' => 1_000_000, 'age_years' => 1],
        calculatedByUserId: null,
    );

    // engine's own 10% default
    expect($calculation->computed_details['scrap_deduction_amount'])->toBe(100_000.0);
});
