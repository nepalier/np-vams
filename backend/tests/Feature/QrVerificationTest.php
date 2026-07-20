<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Domain\Report\Models\DigitalSignature;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportVersion;
use App\Domain\Report\Services\QrVerificationService;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $organization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Everest Valuation Associates',
        'organization_type' => 'valuation_firm',
    ]);

    $client = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Test Bank', 'client_type' => 'commercial_bank']);

    $this->assignment = ValuationAssignment::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $organization->id,
        'assignment_number' => 'VAL-2082-000001',
        'fiscal_year_id' => FiscalYear::where('is_current', true)->first()->id,
        'client_id' => $client->id,
        'assignment_date' => now(),
        'priority' => 'normal',
        'valuation_purpose_id' => ValuationPurpose::first()->id,
        'status' => 'report_issued',
    ]);

    $this->report = Report::create([
        'tenant_id' => $this->tenant->id,
        'valuation_assignment_id' => $this->assignment->id,
        'report_number' => 'RPT-2082-000001',
        'status' => 'issued',
    ]);

    $version = ReportVersion::create([
        'tenant_id' => $this->tenant->id,
        'report_id' => $this->report->id,
        'version_number' => 1,
        'format' => 'pdf',
        'file_path' => 'fake/path.pdf',
        'file_hash_sha256' => hash('sha256', 'fake'),
        'generated_at' => now(),
    ]);
    $this->report->update(['current_version_id' => $version->id]);

    $signer = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ram Sharma']);
    DigitalSignature::create([
        'tenant_id' => $this->tenant->id,
        'report_version_id' => $version->id,
        'signed_by_user_id' => $signer->id,
        'signer_name' => 'Ram Sharma',
        'signed_file_hash_sha256' => $version->file_hash_sha256,
        'signed_at' => now(),
    ]);

    $this->verification = app(QrVerificationService::class)->issueToken($this->report);
});

test('the public payload exposes only the allow-listed safe fields', function () {
    $payload = app(QrVerificationService::class)->publicPayload($this->verification->public_token);

    expect($payload)->toHaveKeys([
        'report_number', 'valuation_firm', 'report_date', 'property_district',
        'property_municipality', 'status', 'revision_number', 'signed_by_name',
    ]);
    expect($payload['report_number'])->toBe('RPT-2082-000001');
    expect($payload['signed_by_name'])->toBe('Ram Sharma');
    expect($payload['status'])->toBe('valid');

    // Never present, under any key name -- loan amount, citizenship number,
    // owner contact info, and full financial detail are simply not part of
    // this array at all.
    $serialized = json_encode($payload);
    expect($serialized)->not->toContain('citizenship');
    expect($serialized)->not->toContain('loan');
});

test('an unknown token returns null rather than throwing, so the controller can 404 uniformly', function () {
    $payload = app(QrVerificationService::class)->publicPayload('this-token-does-not-exist');

    expect($payload)->toBeNull();
});

test('revoking a verification is reflected in the public payload status', function () {
    app(QrVerificationService::class)->revoke($this->verification, 'cancelled');

    $payload = app(QrVerificationService::class)->publicPayload($this->verification->public_token);

    expect($payload['status'])->toBe('cancelled');
});
