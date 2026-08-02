<?php

use App\Domain\Billing\Models\Invoice;
use App\Domain\Client\Models\Client;
use App\Domain\ClientPortal\Services\ClientPortalUserService;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\Notification\Notifications\InvoiceEventNotification;
use App\Models\Tenant;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->client = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Bank', 'client_type' => 'commercial_bank']);
    $this->portalUser = app(ClientPortalUserService::class)->invite($this->client, ['name' => 'Portal User', 'email' => 'portal@bank.example'])['user'];
});

function makeInvoiceDueOn(string $tenantId, string $clientId, string $dueDate, float $outstanding, string $status = 'issued'): Invoice
{
    return Invoice::create([
        'tenant_id' => $tenantId, 'client_id' => $clientId, 'invoice_number' => 'INV-TEST-'.uniqid(),
        'fiscal_year_id' => FiscalYear::where('is_current', true)->first()->id,
        'issue_date' => now()->subDays(30), 'due_date' => $dueDate,
        'total_amount' => $outstanding, 'outstanding_amount' => $outstanding, 'status' => $status,
    ]);
}

test('an invoice past its due date with an outstanding balance triggers a payment_overdue notification', function () {
    Notification::fake();

    makeInvoiceDueOn($this->tenant->id, $this->client->id, now()->subDays(5)->toDateString(), 25000);

    $this->artisan('npvams:check-overdue-invoices')->assertSuccessful();

    Notification::assertSentTo($this->portalUser, InvoiceEventNotification::class);
});

test('an invoice not yet due is not notified', function () {
    Notification::fake();

    makeInvoiceDueOn($this->tenant->id, $this->client->id, now()->addDays(10)->toDateString(), 25000);

    $this->artisan('npvams:check-overdue-invoices')->assertSuccessful();

    Notification::assertNothingSent();
});

test('an overdue invoice that has already been fully paid (zero outstanding) is not notified', function () {
    Notification::fake();

    makeInvoiceDueOn($this->tenant->id, $this->client->id, now()->subDays(5)->toDateString(), 0, 'paid');

    $this->artisan('npvams:check-overdue-invoices')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a cancelled invoice, even if past due with a nonzero recorded balance, is not notified', function () {
    Notification::fake();

    makeInvoiceDueOn($this->tenant->id, $this->client->id, now()->subDays(5)->toDateString(), 25000, 'cancelled');

    $this->artisan('npvams:check-overdue-invoices')->assertSuccessful();

    Notification::assertNothingSent();
});

test('checks across ALL tenants, not just the currently-bound one -- this command runs outside any single tenant context', function () {
    Notification::fake();

    $otherTenant = Tenant::factory()->create();
    $otherClient = Client::create(['tenant_id' => $otherTenant->id, 'name_en' => 'Other Bank', 'client_type' => 'commercial_bank']);
    $otherPortalUser = app(ClientPortalUserService::class)->invite($otherClient, ['name' => 'Other Portal User', 'email' => 'other@bank.example'])['user'];

    makeInvoiceDueOn($otherTenant->id, $otherClient->id, now()->subDays(5)->toDateString(), 15000);

    $this->artisan('npvams:check-overdue-invoices')->assertSuccessful();

    Notification::assertSentTo($otherPortalUser, InvoiceEventNotification::class);
});
