<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Domain\Workflow\Services\WorkflowEngine;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkflowSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);
    $this->seed(WorkflowSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->client = Client::create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Test Bank',
        'client_type' => 'commercial_bank',
    ]);

    $this->assignment = ValuationAssignment::create([
        'tenant_id' => $this->tenant->id,
        'assignment_number' => 'VAL-2082-000001',
        'fiscal_year_id' => FiscalYear::where('is_current', true)->first()->id,
        'client_id' => $this->client->id,
        'assignment_date' => now(),
        'priority' => 'normal',
        'valuation_purpose_id' => ValuationPurpose::first()->id,
        'status' => 'draft',
    ]);
});

test('a valid transition with no role restriction succeeds and writes an immutable log row', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $transition = app(WorkflowEngine::class)->transition($this->assignment, 'submitted', $user);

    expect($this->assignment->fresh()->status)->toBe('submitted');
    expect($transition->previous_status)->toBe('draft');
    expect($transition->new_status)->toBe('submitted');
    expect($transition->user_id)->toBe($user->id);
});

test('a transition not present in the configured graph is rejected', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    app(WorkflowEngine::class)->transition($this->assignment, 'approved', $user);
})->throws(RuntimeException::class);

test('a role-restricted transition is rejected for a user without the required role', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    app(WorkflowEngine::class)->transition($this->assignment, 'submitted', $user);

    // submitted -> assignment_accepted requires Valuation Firm Administrator or Branch Administrator
    app(WorkflowEngine::class)->transition($this->assignment, 'assignment_accepted', $user);
})->throws(RuntimeException::class);

test('a role-restricted transition succeeds once the user holds the required role', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole('Valuation Firm Administrator');

    app(WorkflowEngine::class)->transition($this->assignment, 'submitted', $user);
    app(WorkflowEngine::class)->transition($this->assignment, 'assignment_accepted', $user);

    expect($this->assignment->fresh()->status)->toBe('assignment_accepted');
});

test('a transition that requires remarks is rejected without them', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    app(WorkflowEngine::class)->transition($this->assignment, 'submitted', $user);

    app(WorkflowEngine::class)->transition($this->assignment, 'cancelled', $user); // requires_remarks = true, none given
})->throws(RuntimeException::class);

test('availableTransitions reflects the configured graph for the current status', function () {
    $transitions = app(WorkflowEngine::class)->availableTransitions($this->assignment);

    expect($transitions)->toContain('submitted')->toContain('cancelled');
    expect($transitions)->not->toContain('approved');
});
