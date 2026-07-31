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

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->service = app(ValuationCalculationService::class);
});

function makeAssignmentForClient(string $tenantId, Client $client): ValuationAssignment
{
    $fiscalYear = FiscalYear::where('is_current', true)->first();

    return ValuationAssignment::create([
        'tenant_id' => $tenantId, 'assignment_number' => 'VAL-TEST-'.uniqid(),
        'fiscal_year_id' => $fiscalYear->id, 'client_id' => $client->id,
        'assignment_date' => now(), 'priority' => 'normal',
        'valuation_purpose_id' => ValuationPurpose::first()->id, 'status' => 'draft',
    ]);
}

test('a client with no configured convention falls back to the engine default (30/70)', function () {
    $client = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'No Convention Bank', 'client_type' => 'commercial_bank']);
    $assignment = makeAssignmentForClient($this->tenant->id, $client);

    $calculation = $this->service->runWeightedLandRate(
        tenantId: $this->tenant->id, assignmentId: $assignment->id, propertyId: null,
        input: ['plots' => [['plot_label' => 'A', 'area_considered' => 1, 'government_rate' => 1_000_000, 'market_rate' => 2_000_000]]],
        calculatedByUserId: null,
    );

    // 30% x 1M + 70% x 2M = 300,000 + 1,400,000 = 1,700,000
    expect($calculation->computed_details['plots'][0]['weighted_rate'])->toBe(1_700_000.0);
});

test('a client WITH a configured convention (e.g. the real 20/80 Excel-reference split) overrides the engine default', function () {
    $client = Client::create([
        'tenant_id' => $this->tenant->id, 'name_en' => '20/80 Bank', 'client_type' => 'commercial_bank',
        'land_rate_government_weight_pct' => 20, 'land_rate_market_weight_pct' => 80,
    ]);
    $assignment = makeAssignmentForClient($this->tenant->id, $client);

    $calculation = $this->service->runWeightedLandRate(
        tenantId: $this->tenant->id, assignmentId: $assignment->id, propertyId: null,
        input: ['plots' => [['plot_label' => 'A', 'area_considered' => 1, 'government_rate' => 1_000_000, 'market_rate' => 2_000_000]]],
        calculatedByUserId: null,
    );

    // 20% x 1M + 80% x 2M = 200,000 + 1,600,000 = 1,800,000
    expect($calculation->computed_details['plots'][0]['weighted_rate'])->toBe(1_800_000.0);
});

test('an explicit request-level override takes priority over the client\'s own configured convention', function () {
    $client = Client::create([
        'tenant_id' => $this->tenant->id, 'name_en' => '70/30 Bank', 'client_type' => 'commercial_bank',
        'land_rate_government_weight_pct' => 70, 'land_rate_market_weight_pct' => 30,
    ]);
    $assignment = makeAssignmentForClient($this->tenant->id, $client);

    $calculation = $this->service->runWeightedLandRate(
        tenantId: $this->tenant->id, assignmentId: $assignment->id, propertyId: null,
        input: [
            'government_weight_pct' => 50, 'market_weight_pct' => 50, // explicit override for this one calculation
            'plots' => [['plot_label' => 'A', 'area_considered' => 1, 'government_rate' => 1_000_000, 'market_rate' => 2_000_000]],
        ],
        calculatedByUserId: null,
    );

    // override wins: 50% x 1M + 50% x 2M = 1,500,000, NOT the client's 70/30 (which would give 1,300,000)
    expect($calculation->computed_details['plots'][0]['weighted_rate'])->toBe(1_500_000.0);
});
