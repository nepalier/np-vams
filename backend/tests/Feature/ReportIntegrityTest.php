<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Services\ReportIntegrityService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);
    Storage::fake(config('npvams.documents.disk'));

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $client = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Bank', 'client_type' => 'commercial_bank']);

    $this->assignment = ValuationAssignment::create([
        'tenant_id' => $this->tenant->id,
        'assignment_number' => 'VAL-2082-000001',
        'fiscal_year_id' => FiscalYear::where('is_current', true)->first()->id,
        'client_id' => $client->id,
        'assignment_date' => now(),
        'priority' => 'normal',
        'valuation_purpose_id' => ValuationPurpose::first()->id,
        'status' => 'draft',
    ]);

    $this->report = Report::create([
        'tenant_id' => $this->tenant->id,
        'valuation_assignment_id' => $this->assignment->id,
        'status' => 'drafting',
    ]);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

test('creating a version stores the file, computes its hash, and points the report at it', function () {
    $version = app(ReportIntegrityService::class)->createVersion($this->report, '%PDF-1.4 fake pdf contents', 'pdf', $this->user->id);

    expect($version->version_number)->toBe(1);
    expect($version->file_hash_sha256)->toBe(hash('sha256', '%PDF-1.4 fake pdf contents'));
    expect($this->report->fresh()->current_version_id)->toBe($version->id);
});

test('a locked report refuses a new version without a supersede reason', function () {
    app(ReportIntegrityService::class)->createVersion($this->report, 'v1 contents', 'pdf', $this->user->id);
    app(ReportIntegrityService::class)->lock($this->report);

    app(ReportIntegrityService::class)->createVersion($this->report->fresh(), 'v2 contents', 'pdf', $this->user->id);
})->throws(RuntimeException::class);

test('a locked report accepts a new version when a supersede reason is given, and marks the old version superseded', function () {
    $v1 = app(ReportIntegrityService::class)->createVersion($this->report, 'v1 contents', 'pdf', $this->user->id);
    app(ReportIntegrityService::class)->lock($this->report);

    $v2 = app(ReportIntegrityService::class)->createVersion(
        $this->report->fresh(),
        'v2 contents — post-approval correction',
        'pdf',
        $this->user->id,
        supersedeReason: 'Corrected a transposed figure in the valuation summary table.'
    );

    expect($v2->version_number)->toBe(2);
    expect($v1->fresh()->superseded_by_id)->toBe($v2->id);
    expect($this->report->fresh()->current_version_id)->toBe($v2->id);
});

test('tamper detection catches a file that was altered on disk after generation', function () {
    $version = app(ReportIntegrityService::class)->createVersion($this->report, 'original contents', 'pdf', $this->user->id);

    expect(app(ReportIntegrityService::class)->verifyIntegrity($version))->toBeTrue();

    Storage::disk($version->storage_disk)->put($version->file_path, 'tampered contents');

    expect(app(ReportIntegrityService::class)->verifyIntegrity($version))->toBeFalse();
});
