<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\Dashboard\Services\ValuationFirmDashboardService;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Models\Tenant;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->client = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Bank A', 'client_type' => 'commercial_bank']);
    $this->fiscalYear = FiscalYear::where('is_current', true)->first();
    $this->purpose = ValuationPurpose::first();

    $this->makeAssignment = function (string $number, string $status, ?string $tenantId = null, ?string $clientId = null) {
        return ValuationAssignment::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'assignment_number' => $number,
            'fiscal_year_id' => $this->fiscalYear->id,
            'client_id' => $clientId ?? $this->client->id,
            'assignment_date' => now(),
            'priority' => 'normal',
            'valuation_purpose_id' => $this->purpose->id,
            'status' => $status,
        ]);
    };
});

test('new_assignments_last_30_days counts recently created assignments for this tenant only', function () {
    ($this->makeAssignment)('VAL-2082-000001', 'draft');
    ($this->makeAssignment)('VAL-2082-000002', 'submitted');

    $otherTenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $otherTenant->id);
    $otherClient = Client::create(['tenant_id' => $otherTenant->id, 'name_en' => 'Other Bank', 'client_type' => 'commercial_bank']);
    ($this->makeAssignment)('VAL-2082-000001', 'draft', $otherTenant->id, $otherClient->id);

    app()->instance('currentTenantId', $this->tenant->id);

    $summary = app(ValuationFirmDashboardService::class)->summary();

    // Exactly this tenant's 2 assignments, never the other tenant's 1 --
    // proves the dashboard rides on TenantScope rather than needing its
    // own manual filter.
    expect($summary['new_assignments_last_30_days'])->toBe(2);
});

test('client_wise_assignment_count groups correctly', function () {
    ($this->makeAssignment)('VAL-2082-000001', 'draft');
    ($this->makeAssignment)('VAL-2082-000002', 'submitted');

    $summary = app(ValuationFirmDashboardService::class)->summary();

    expect($summary['client_wise_assignment_count'][$this->client->id])->toBe(2);
});

test('reports_under_review counts only assignments actually in that status', function () {
    ($this->makeAssignment)('VAL-2082-000001', 'under_technical_review');
    ($this->makeAssignment)('VAL-2082-000002', 'draft');

    $summary = app(ValuationFirmDashboardService::class)->summary();

    expect($summary['reports_under_review'])->toBe(1);
});
