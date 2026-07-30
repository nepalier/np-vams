<?php

use App\Domain\Property\Services\PropertyCodeGenerator;
use App\Models\Tenant;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);
    $this->generator = app(PropertyCodeGenerator::class);
});

test('generates sequential codes starting from PROP-000001', function () {
    expect($this->generator->next($this->tenant->id))->toBe('PROP-000001');
});

test('numbering is isolated per tenant', function () {
    $otherTenant = Tenant::factory()->create();

    expect($this->generator->next($this->tenant->id))->toBe('PROP-000001');
    expect($this->generator->next($otherTenant->id))->toBe('PROP-000001');
});
