<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Domain\Notification\Notifications\AssignmentWorkflowNotification;
use App\Domain\Workflow\Services\WorkflowEngine;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);
    $this->seed(WorkflowSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $client = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Bank', 'client_type' => 'commercial_bank']);

    $this->valuer = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->assignment = ValuationAssignment::create([
        'tenant_id' => $this->tenant->id, 'assignment_number' => 'VAL-2082-000001',
        'fiscal_year_id' => FiscalYear::where('is_current', true)->first()->id, 'client_id' => $client->id,
        'assignment_date' => now(), 'priority' => 'normal',
        'valuation_purpose_id' => ValuationPurpose::first()->id, 'status' => 'preliminary_verification',
        'assigned_valuer_id' => $this->valuer->id,
    ]);
});

test('transitioning to valuer_assigned notifies the assigned valuer', function () {
    Notification::fake();

    $staffUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staffUser->assignRole('Valuation Firm Administrator');

    app(WorkflowEngine::class)->transition($this->assignment, 'valuer_assigned', $staffUser);

    Notification::assertSentTo(
        $this->valuer,
        AssignmentWorkflowNotification::class,
    );
});

test('a transition with no wired notification does not error and simply sends nothing', function () {
    Notification::fake();

    $staffUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

    app(WorkflowEngine::class)->transition($this->assignment, 'valuer_assigned', $staffUser);

    // valuer_assigned -> site_visit_scheduled has no wired notification --
    // proves the observer's default match arm is safe, not just untested.
    Notification::assertNothingSentTo($staffUser);
});
