<?php

use App\Domain\Professional\Models\ProfessionalProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    Sanctum::actingAs($this->user, [], 'web');
});

test('a user can set their own professional profile without any special role', function () {
    $response = $this->putJson('/api/v1/professional-profile', [
        'nec_registration_number' => 'NEC-1234', 'license_expiry_date' => now()->addYear()->toDateString(),
    ]);

    $response->assertOk();
    expect(ProfessionalProfile::where('user_id', $this->user->id)->first()->nec_registration_number)->toBe('NEC-1234');
});

test('saving twice updates the same profile rather than creating a duplicate', function () {
    $this->putJson('/api/v1/professional-profile', ['nec_registration_number' => 'NEC-1111']);
    $this->putJson('/api/v1/professional-profile', ['nec_registration_number' => 'NEC-2222']);

    expect(ProfessionalProfile::where('user_id', $this->user->id)->count())->toBe(1);
    expect(ProfessionalProfile::where('user_id', $this->user->id)->first()->nec_registration_number)->toBe('NEC-2222');
});

test('a regular user without an admin role cannot view the firm-wide compliance overview', function () {
    $response = $this->getJson('/api/v1/professional-profiles');

    $response->assertStatus(403);
});

test('a Tenant Administrator CAN view the firm-wide compliance overview', function () {
    $this->user->assignRole('Tenant Administrator');

    ProfessionalProfile::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'nec_registration_number' => 'NEC-9999']);

    $response = $this->getJson('/api/v1/professional-profiles');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('nec_registration_number'))->toContain('NEC-9999');
});

test('profiles with no expiry date recorded sort AFTER profiles with a real date, not before -- proves the MySQL-compatible NULLS-LAST ordering fix actually works', function () {
    $this->user->assignRole('Tenant Administrator');

    $noDateUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    ProfessionalProfile::create(['tenant_id' => $this->tenant->id, 'user_id' => $noDateUser->id, 'nec_registration_number' => 'NO-DATE']);

    $withDateUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    ProfessionalProfile::create([
        'tenant_id' => $this->tenant->id, 'user_id' => $withDateUser->id, 'nec_registration_number' => 'HAS-DATE',
        'license_expiry_date' => now()->addDays(5)->toDateString(),
    ]);

    $response = $this->getJson('/api/v1/professional-profiles');

    $regNumbers = collect($response->json('data'))->pluck('nec_registration_number')->toArray();

    expect(array_search('HAS-DATE', $regNumbers))->toBeLessThan(array_search('NO-DATE', $regNumbers));
});
