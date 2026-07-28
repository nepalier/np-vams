<?php

use App\Domain\Assignment\Http\Resources\AssignmentResource;
use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Models\Tenant;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->client = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Everest Bank', 'client_type' => 'commercial_bank']);

    $this->assignment = ValuationAssignment::create([
        'tenant_id' => $this->tenant->id, 'assignment_number' => 'VAL-2082-000001',
        'fiscal_year_id' => FiscalYear::where('is_current', true)->first()->id, 'client_id' => $this->client->id,
        'assignment_date' => now(), 'priority' => 'normal',
        'valuation_purpose_id' => ValuationPurpose::first()->id, 'status' => 'draft',
    ]);
});

test('client_name and valuation_purpose_name are present when the relations are eager-loaded', function () {
    $loaded = $this->assignment->load(['client', 'valuationPurpose']);

    $array = (new AssignmentResource($loaded))->response()->getData(true)['data'];

    expect($array['client_name'])->toBe('Everest Bank');
    expect($array['valuation_purpose_name'])->not->toBeNull();
});

test('client_name is simply absent (not an error) when the relation was not eager-loaded', function () {
    $unloaded = ValuationAssignment::find($this->assignment->id);

    $array = (new AssignmentResource($unloaded))->response()->getData(true)['data'];

    expect(array_key_exists('client_name', $array))->toBeFalse();
});
