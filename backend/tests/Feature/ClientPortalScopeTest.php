<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\ClientPortal\Services\ClientPortalUserService;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->clientA = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Bank A', 'client_type' => 'commercial_bank']);
    $this->clientB = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Bank B', 'client_type' => 'commercial_bank']);

    $fiscalYear = FiscalYear::where('is_current', true)->first();
    $purpose = ValuationPurpose::first();

    $this->assignmentA = ValuationAssignment::create([
        'tenant_id' => $this->tenant->id, 'assignment_number' => 'VAL-2082-000001', 'fiscal_year_id' => $fiscalYear->id,
        'client_id' => $this->clientA->id, 'assignment_date' => now(), 'priority' => 'normal',
        'valuation_purpose_id' => $purpose->id, 'status' => 'draft',
    ]);

    $this->assignmentB = ValuationAssignment::create([
        'tenant_id' => $this->tenant->id, 'assignment_number' => 'VAL-2082-000002', 'fiscal_year_id' => $fiscalYear->id,
        'client_id' => $this->clientB->id, 'assignment_date' => now(), 'priority' => 'normal',
        'valuation_purpose_id' => $purpose->id, 'status' => 'draft',
    ]);

    $invite = app(ClientPortalUserService::class)->invite($this->clientA, [
        'name' => 'Bank A Portal User', 'email' => 'porta@bankA.example.com',
    ]);
    $this->portalUserA = $invite['user'];
});

test('a client-portal user only sees their own client\'s assignments, never another client\'s within the same tenant', function () {
    app()->instance('currentTenantId', $this->tenant->id);
    app()->instance('currentClientId', $this->portalUserA->client_id);

    $visible = ValuationAssignment::all();

    expect($visible->pluck('id'))->toContain($this->assignmentA->id);
    expect($visible->pluck('id'))->not->toContain($this->assignmentB->id);
});

test('a tenant staff user (no client_id bound) sees every client\'s assignments, unaffected by the scope', function () {
    app()->instance('currentTenantId', $this->tenant->id);
    // currentClientId deliberately never bound -- simulates a staff request

    $visible = ValuationAssignment::all();

    expect($visible->pluck('id'))->toContain($this->assignmentA->id);
    expect($visible->pluck('id'))->toContain($this->assignmentB->id);
});

test('withoutClientPortalScope bypasses the restriction only when explicitly invoked', function () {
    app()->instance('currentTenantId', $this->tenant->id);
    app()->instance('currentClientId', $this->portalUserA->client_id);

    expect(ValuationAssignment::count())->toBe(1);
    expect(ValuationAssignment::withoutClientPortalScope()->count())->toBe(2);
});

test('a client-portal user is created without an organization_id, only a client_id', function () {
    expect($this->portalUserA->organization_id)->toBeNull();
    expect($this->portalUserA->client_id)->toBe($this->clientA->id);
    expect($this->portalUserA->isClientPortalUser())->toBeTrue();
});

test('a client-portal user is assigned the requested role', function () {
    expect($this->portalUserA->hasRole('Client Institution Administrator'))->toBeTrue();
});

test('AssignmentPolicy denies a client-portal user direct access to another client\'s assignment', function () {
    $policy = new \App\Domain\Assignment\Policies\AssignmentPolicy;

    expect($policy->view($this->portalUserA, $this->assignmentA))->toBeTrue();
    expect($policy->view($this->portalUserA, $this->assignmentB))->toBeFalse();
});
