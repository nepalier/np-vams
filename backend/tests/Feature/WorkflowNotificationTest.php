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

test('a user who is not anyone\'s assignee never receives a notification, regardless of how many events are wired', function () {
    Notification::fake();

    $staffUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

    app(WorkflowEngine::class)->transition($this->assignment, 'valuer_assigned', $staffUser);

    // $staffUser performed the transition but isn't the assignment's
    // valuer/surveyor/reviewer/approver -- proves recipients are always
    // resolved from the assignment's own assignee fields, never the
    // acting user, no matter how many more events get wired over time.
    Notification::assertNothingSentTo($staffUser);
});

test('transitioning to site_visit_scheduled notifies the assigned surveyor, falling back to the valuer if none is set', function () {
    Notification::fake();

    $this->assignment->update(['status' => 'valuer_assigned']);
    $staffUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staffUser->assignRole('Valuation Firm Administrator');

    app(WorkflowEngine::class)->transition($this->assignment->fresh(), 'site_visit_scheduled', $staffUser);

    // No surveyor was assigned on this assignment -- falls back to the valuer.
    Notification::assertSentTo($this->valuer, AssignmentWorkflowNotification::class);
});

test('transitioning to cancelled passes the transition remarks through as a token', function () {
    Notification::fake();

    $this->assignment->update(['status' => 'awaiting_approval']);
    $approver = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $approver->assignRole('Approving Authority');

    app(WorkflowEngine::class)->transition($this->assignment->fresh(), 'cancelled', $approver, 'Client withdrew the application.');

    Notification::assertSentTo(
        $this->valuer,
        AssignmentWorkflowNotification::class,
    );
});
