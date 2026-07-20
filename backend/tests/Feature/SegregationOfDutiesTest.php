<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Domain\Review\Services\ApprovalService;
use App\Domain\Review\Services\ReviewService;
use App\Models\Organization;
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

    $this->organization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Test Firm',
        'organization_type' => 'valuation_firm',
        'allow_segregation_of_duties_exception' => false,
    ]);

    $this->valuer = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->valuer->assignRole('Valuer or Engineer');

    $this->reviewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->reviewer->assignRole('Technical Reviewer');

    $this->approver = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->approver->assignRole('Approving Authority');

    $client = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Bank', 'client_type' => 'commercial_bank']);

    $this->assignment = ValuationAssignment::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'assignment_number' => 'VAL-2082-000001',
        'fiscal_year_id' => FiscalYear::where('is_current', true)->first()->id,
        'client_id' => $client->id,
        'assignment_date' => now(),
        'priority' => 'normal',
        'valuation_purpose_id' => ValuationPurpose::first()->id,
        'status' => 'under_technical_review',
        'assigned_valuer_id' => $this->valuer->id,
    ]);
});

test('the assigned valuer cannot also technically review their own assignment', function () {
    app(ReviewService::class)->recordTechnicalReviewDecision($this->assignment, $this->valuer, 'accept', null);
})->throws(RuntimeException::class);

test('a distinct reviewer can review the assignment', function () {
    $this->assignment->update(['assigned_reviewer_id' => $this->reviewer->id]);

    $record = app(ReviewService::class)->recordTechnicalReviewDecision($this->assignment, $this->reviewer, 'recommend_approval', null);

    expect($record->decision)->toBe('recommend_approval');
    expect($this->assignment->fresh()->status)->toBe('awaiting_approval');
});

test('the assigned reviewer cannot also give final approval on the same assignment', function () {
    $this->assignment->update([
        'assigned_reviewer_id' => $this->reviewer->id,
        'status' => 'awaiting_approval',
        'assigned_approver_id' => $this->reviewer->id, // same person as reviewer
    ]);

    app(ApprovalService::class)->decide($this->assignment, $this->reviewer, 'approve', null);
})->throws(RuntimeException::class);

test('the segregation-of-duties exception, when explicitly enabled, permits the same user to review and approve', function () {
    $this->organization->update(['allow_segregation_of_duties_exception' => true]);
    $this->reviewer->assignRole('Approving Authority'); // same person now legitimately holds both roles

    $this->assignment->update([
        'status' => 'awaiting_approval',
        'assigned_reviewer_id' => $this->reviewer->id,
        'assigned_approver_id' => $this->reviewer->id,
    ]);

    $record = app(ApprovalService::class)->decide($this->assignment, $this->reviewer, 'approve', null);

    expect($record->decision)->toBe('approve');
    expect($this->assignment->fresh()->status)->toBe('approved');
});
