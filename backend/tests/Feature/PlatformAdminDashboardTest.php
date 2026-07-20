<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\Dashboard\Services\PlatformAdminDashboardService;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Models\Tenant;
use Database\Seeders\MasterDataSeeder;

test('the platform dashboard aggregates across every tenant, unlike the firm dashboard', function () {
    (new MasterDataSeeder)->run();

    $fiscalYear = FiscalYear::where('is_current', true)->first();
    $purpose = ValuationPurpose::first();

    $tenantA = Tenant::factory()->create();
    app()->instance('currentTenantId', $tenantA->id);
    $clientA = Client::create(['tenant_id' => $tenantA->id, 'name_en' => 'A Bank', 'client_type' => 'commercial_bank']);
    ValuationAssignment::create([
        'tenant_id' => $tenantA->id, 'assignment_number' => 'VAL-2082-000001', 'fiscal_year_id' => $fiscalYear->id,
        'client_id' => $clientA->id, 'assignment_date' => now(), 'priority' => 'normal',
        'valuation_purpose_id' => $purpose->id, 'status' => 'draft',
    ]);

    $tenantB = Tenant::factory()->create();
    app()->instance('currentTenantId', $tenantB->id);
    $clientB = Client::create(['tenant_id' => $tenantB->id, 'name_en' => 'B Bank', 'client_type' => 'commercial_bank']);
    ValuationAssignment::create([
        'tenant_id' => $tenantB->id, 'assignment_number' => 'VAL-2082-000001', 'fiscal_year_id' => $fiscalYear->id,
        'client_id' => $clientB->id, 'assignment_date' => now(), 'priority' => 'normal',
        'valuation_purpose_id' => $purpose->id, 'status' => 'draft',
    ]);
    ValuationAssignment::create([
        'tenant_id' => $tenantB->id, 'assignment_number' => 'VAL-2082-000002', 'fiscal_year_id' => $fiscalYear->id,
        'client_id' => $clientB->id, 'assignment_date' => now(), 'priority' => 'normal',
        'valuation_purpose_id' => $purpose->id, 'status' => 'draft',
    ]);

    app()->forgetInstance('currentTenantId'); // platform view -- no single tenant context

    $summary = app(PlatformAdminDashboardService::class)->summary();

    expect($summary['total_tenants'])->toBeGreaterThanOrEqual(2);
    expect($summary['total_assignments'])->toBeGreaterThanOrEqual(3); // sees BOTH tenants' assignments
});
