<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a user can log in with valid credentials and receives a token', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $tenant->id);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'valuer@example.com',
        'password' => Hash::make('CorrectHorseBatteryStaple9'),
        'is_active' => true,
        'mfa_enabled' => false,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'valuer@example.com',
        'password' => 'CorrectHorseBatteryStaple9',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email', 'roles', 'permissions']]]);
});

test('an inactive user cannot log in even with correct credentials', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $tenant->id);

    User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'inactive@example.com',
        'password' => Hash::make('CorrectHorseBatteryStaple9'),
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'inactive@example.com',
        'password' => 'CorrectHorseBatteryStaple9',
    ])->assertStatus(422);
});

test('wrong password is rejected', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $tenant->id);

    User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'valuer2@example.com',
        'password' => Hash::make('CorrectHorseBatteryStaple9'),
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'valuer2@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(422);
});
