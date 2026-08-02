<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\Document\Models\PropertyDocument;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Sanctum;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);
    Storage::fake(config('npvams.documents.disk'));

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('Tenant Administrator');
    Sanctum::actingAs($this->user, [], 'web');

    $client = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Bank', 'client_type' => 'commercial_bank']);
    $this->assignment = ValuationAssignment::create([
        'tenant_id' => $this->tenant->id, 'assignment_number' => 'VAL-TEST-'.uniqid(),
        'fiscal_year_id' => FiscalYear::where('is_current', true)->first()->id, 'client_id' => $client->id,
        'assignment_date' => now(), 'priority' => 'normal', 'valuation_purpose_id' => ValuationPurpose::first()->id, 'status' => 'draft',
    ]);
});

test('uploading a document persists it with a real SHA-256 hash, defaulting to a received status', function () {
    $response = $this->postJson("/api/v1/assignments/{$this->assignment->id}/documents", [
        'file' => UploadedFile::fake()->create('lalpurja.pdf', 500, 'application/pdf'),
        'category' => 'land', 'document_type' => 'Lalpurja',
    ]);

    $response->assertStatus(201);
    $document = PropertyDocument::where('documentable_id', $this->assignment->id)->first();
    expect($document->file_hash_sha256)->toHaveLength(64);
    expect($document->documentable_type)->toBe(ValuationAssignment::class);
});

test('uploading the exact same file twice for the same assignment is rejected as a duplicate', function () {
    $file = UploadedFile::fake()->create('citizenship.pdf', 200, 'application/pdf');
    $this->postJson("/api/v1/assignments/{$this->assignment->id}/documents", [
        'file' => $file, 'category' => 'identity_organizational', 'document_type' => 'Citizenship',
    ])->assertStatus(201);

    $duplicateFile = UploadedFile::fake()->createWithContent('citizenship-again.pdf', file_get_contents($file->getRealPath()));

    $response = $this->postJson("/api/v1/assignments/{$this->assignment->id}/documents", [
        'file' => $duplicateFile, 'category' => 'identity_organizational', 'document_type' => 'Citizenship',
    ]);

    $response->assertStatus(422);
});

test('updating a document\'s verification status persists correctly', function () {
    $document = PropertyDocument::create([
        'tenant_id' => $this->tenant->id, 'documentable_type' => ValuationAssignment::class, 'documentable_id' => $this->assignment->id,
        'category' => 'land', 'document_type' => 'Lalpurja', 'verification_status' => 'received',
        'storage_disk' => config('npvams.documents.disk'), 'file_path' => 'fake/path.pdf', 'file_hash_sha256' => str_repeat('a', 64),
        'current_version' => 1,
    ]);

    $response = $this->putJson("/api/v1/documents/{$document->id}/verification", [
        'verification_status' => 'authority_verified', 'authority_verified' => true,
    ]);

    $response->assertOk();
    expect($document->fresh()->verification_status)->toBe('authority_verified');
});
