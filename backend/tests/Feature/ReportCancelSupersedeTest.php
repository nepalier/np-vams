<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Services\ReportWorkflowService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\WorkflowSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);
    $this->seed(WorkflowSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $client = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Bank', 'client_type' => 'commercial_bank']);

    $this->assignment = ValuationAssignment::create([
        'tenant_id' => $this->tenant->id, 'assignment_number' => 'VAL-2082-000001',
        'fiscal_year_id' => FiscalYear::where('is_current', true)->first()->id, 'client_id' => $client->id,
        'assignment_date' => now(), 'priority' => 'normal',
        'valuation_purpose_id' => ValuationPurpose::first()->id, 'status' => 'report_issued',
    ]);

    $this->report = Report::create([
        'tenant_id' => $this->tenant->id, 'valuation_assignment_id' => $this->assignment->id,
        'client_id' => $client->id, 'report_number' => 'RPT-2082-000001', 'status' => 'issued',
    ]);

    $this->approver = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->approver->assignRole('Approving Authority');

    \App\Domain\Report\Models\QrVerification::create([
        'tenant_id' => $this->tenant->id,
        'report_id' => $this->report->id,
        'public_token' => \Illuminate\Support\Str::random(40),
        'status' => 'valid',
    ]);
});

test('cancelling an issued report also transitions the assignment workflow, not just the report row', function () {
    $result = app(ReportWorkflowService::class)->cancel($this->report, $this->assignment, $this->approver, 'Client requested cancellation.');

    expect($result->status)->toBe('cancelled');
    expect($this->assignment->fresh()->status)->toBe('cancelled');
});

test('cancelling a report that is not currently issued is rejected', function () {
    $this->report->update(['status' => 'drafting']);

    app(ReportWorkflowService::class)->cancel($this->report, $this->assignment, $this->approver, 'reason');
})->throws(RuntimeException::class);

test('superseding an issued report transitions both the report and the assignment', function () {
    $result = app(ReportWorkflowService::class)->supersede($this->report, $this->assignment, $this->approver, 'Corrected valuation figure.');

    expect($result->status)->toBe('superseded');
    expect($this->assignment->fresh()->status)->toBe('superseded');
    expect($this->report->qrVerification()->first()?->status)->toBe('superseded');
});
