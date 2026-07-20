<?php

use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a user cannot see organizations belonging to another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    app()->instance('currentTenantId', $tenantA->id);
    $orgA = Organization::factory()->create(['tenant_id' => $tenantA->id]);

    app()->instance('currentTenantId', $tenantB->id);
    $orgB = Organization::factory()->create(['tenant_id' => $tenantB->id]);

    // Simulate the IdentifyTenant middleware resolving tenant A for this user.
    app()->instance('currentTenantId', $tenantA->id);

    $visible = Organization::all();

    expect($visible->pluck('id'))->toContain($orgA->id);
    expect($visible->pluck('id'))->not->toContain($orgB->id);
});

test('withoutTenantScope bypasses isolation only when explicitly invoked', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    app()->instance('currentTenantId', $tenantA->id);
    Organization::factory()->create(['tenant_id' => $tenantA->id]);

    app()->instance('currentTenantId', $tenantB->id);
    Organization::factory()->create(['tenant_id' => $tenantB->id]);

    app()->instance('currentTenantId', $tenantA->id);

    expect(Organization::withoutTenantScope()->count())->toBe(2);
    expect(Organization::count())->toBe(1);
});
