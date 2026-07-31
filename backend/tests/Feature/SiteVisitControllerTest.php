<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Domain\SiteVisit\Models\SiteVisit;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

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

test('a site visit can be scheduled for an assignment', function () {
    $response = $this->postJson("/api/v1/assignments/{$this->assignment->id}/site-visits", ['scheduled_at' => now()->addDays(2)->toDateTimeString()]);

    $response->assertStatus(201);
    expect(SiteVisit::where('valuation_assignment_id', $this->assignment->id)->count())->toBe(1);
});

test('completing an inspection is rejected when mandatory information is missing, per Section 17', function () {
    $visit = SiteVisit::create(['tenant_id' => $this->tenant->id, 'valuation_assignment_id' => $this->assignment->id, 'scheduled_at' => now(), 'status' => 'scheduled']);

    $response = $this->postJson("/api/v1/site-visits/{$visit->id}/complete");

    $response->assertStatus(422);
    expect($visit->fresh()->inspection_completed)->toBeFalse();
});

test('completing an inspection succeeds once check-in, owner confirmation, checklist, and GPS are all present', function () {
    $visit = SiteVisit::create(['tenant_id' => $this->tenant->id, 'valuation_assignment_id' => $this->assignment->id, 'scheduled_at' => now(), 'status' => 'scheduled']);

    $this->postJson("/api/v1/site-visits/{$visit->id}/check-in", ['check_in_latitude' => 27.7, 'check_in_longitude' => 85.3])->assertOk();
    $this->putJson("/api/v1/site-visits/{$visit->id}", [
        'owner_representative_confirmed' => true, 'field_checklist' => ['Land measured'],
    ])->assertOk();

    $response = $this->postJson("/api/v1/site-visits/{$visit->id}/complete");

    $response->assertOk();
    expect($visit->fresh()->inspection_completed)->toBeTrue();
});
