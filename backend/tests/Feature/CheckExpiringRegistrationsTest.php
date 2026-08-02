<?php

use App\Domain\Notification\Notifications\RegistrationExpiringNotification;
use App\Domain\Professional\Models\ProfessionalProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

test('a registration expiring within the default 30-day window triggers a notification', function () {
    Notification::fake();

    ProfessionalProfile::create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
        'registration_validity_date' => now()->addDays(15)->toDateString(),
    ]);

    $this->artisan('npvams:check-expiring-registrations')->assertSuccessful();

    Notification::assertSentTo($this->user, RegistrationExpiringNotification::class);
});

test('a registration expiring far in the future is not notified', function () {
    Notification::fake();

    ProfessionalProfile::create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
        'registration_validity_date' => now()->addYear()->toDateString(),
    ]);

    $this->artisan('npvams:check-expiring-registrations')->assertSuccessful();

    Notification::assertNothingSent();
});

test('an inactive profile is not notified even if its registration is expiring soon', function () {
    Notification::fake();

    ProfessionalProfile::create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
        'registration_validity_date' => now()->addDays(10)->toDateString(), 'is_active' => false,
    ]);

    $this->artisan('npvams:check-expiring-registrations')->assertSuccessful();

    Notification::assertNothingSent();
});

test('the --days option changes the alert window', function () {
    Notification::fake();

    ProfessionalProfile::create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
        'registration_validity_date' => now()->addDays(45)->toDateString(),
    ]);

    $this->artisan('npvams:check-expiring-registrations')->assertSuccessful();
    Notification::assertNothingSent(); // 45 days out is beyond the default 30-day window

    $this->artisan('npvams:check-expiring-registrations --days=60')->assertSuccessful();
    Notification::assertSentTo($this->user, RegistrationExpiringNotification::class);
});

test('when both dates are set, the sooner of the two is what gets reported in the notification', function () {
    Notification::fake();

    ProfessionalProfile::create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
        'registration_validity_date' => now()->addDays(20)->toDateString(),
        'license_expiry_date' => now()->addDays(10)->toDateString(), // sooner
    ]);

    $this->artisan('npvams:check-expiring-registrations')->assertSuccessful();

    Notification::assertSentTo($this->user, function (RegistrationExpiringNotification $notification) {
        $array = $notification->toArray((object) ['preferred_locale' => 'en']);

        return $array['expiry_date'] === now()->addDays(10)->toDateString();
    });
});
