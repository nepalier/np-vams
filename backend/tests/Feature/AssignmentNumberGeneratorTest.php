<?php

use App\Domain\Assignment\Services\AssignmentNumberGenerator;
use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\FiscalYear;
use App\Models\Tenant;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);
});

test('generates sequential numbers scoped to tenant and fiscal year', function () {
    $fiscalYear = FiscalYear::where('is_current', true)->first();
    $generator = app(AssignmentNumberGenerator::class);

    $first = $generator->next($this->tenant->id, $fiscalYear);
    expect($first)->toBe('VAL-2082-000001');

    // Simulate the first number actually having been used (the generator
    // counts existing rows, so we need a real row for the sequence to advance).
    \App\Domain\Assignment\Models\ValuationAssignment::create([
        'tenant_id' => $this->tenant->id,
        'assignment_number' => $first,
        'fiscal_year_id' => $fiscalYear->id,
        'client_id' => Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'C1', 'client_type' => 'commercial_bank'])->id,
        'assignment_date' => now(),
        'priority' => 'normal',
        'valuation_purpose_id' => \App\Domain\MasterData\Models\ValuationPurpose::first()->id,
        'status' => 'draft',
    ]);

    $second = $generator->next($this->tenant->id, $fiscalYear);
    expect($second)->toBe('VAL-2082-000002');
});

test('numbering is isolated per tenant', function () {
    $fiscalYear = FiscalYear::where('is_current', true)->first();
    $otherTenant = Tenant::factory()->create();

    $generator = app(AssignmentNumberGenerator::class);

    expect($generator->next($this->tenant->id, $fiscalYear))->toBe('VAL-2082-000001');
    expect($generator->next($otherTenant->id, $fiscalYear))->toBe('VAL-2082-000001');
});
