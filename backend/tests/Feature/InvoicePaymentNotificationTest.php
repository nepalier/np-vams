<?php

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\BillingService;
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
    $this->service = app(BillingService::class);
});

test('creating an invoice notifies the client\'s portal users', function () {
    Notification::fake();

    $portalUser = app(ClientPortalUserService::class)->invite($this->client, ['name' => 'Portal User', 'email' => 'portal@bank.example'])['user'];

    $invoice = $this->service->createInvoice(
        tenantId: $this->tenant->id, clientId: $this->client->id, assignmentId: null,
        items: [['description' => 'Valuation fee', 'quantity' => 1, 'unit_rate' => 50000]],
        vatPct: 13, tdsPct: 1.5, discountAmount: 0, dueDate: now()->addDays(15)->toDateString(), createdByUserId: null,
    );

    Notification::assertSentTo($portalUser, InvoiceEventNotification::class);
});

test('a client with no portal users configured yet simply receives no notification -- not an error', function () {
    Notification::fake();

    $invoice = $this->service->createInvoice(
        tenantId: $this->tenant->id, clientId: $this->client->id, assignmentId: null,
        items: [['description' => 'Valuation fee', 'quantity' => 1, 'unit_rate' => 50000]],
        vatPct: 13, tdsPct: 1.5, discountAmount: 0, dueDate: now()->addDays(15)->toDateString(), createdByUserId: null,
    );

    expect($invoice)->not->toBeNull(); // creation itself succeeded regardless
    Notification::assertNothingSent();
});

/**
 * The critical test: proves the timing-bug fix actually works. An
 * Observer on Payment::created would fire BEFORE outstanding_amount is
 * recalculated in BillingService::recordPayment -- this test would FAIL
 * against that flawed design (asserting the stale pre-payment balance
 * instead), which is exactly why it was replaced with a direct call
 * placed after the recalculation.
 */
test('the payment-received notification carries the CORRECT post-payment outstanding balance, not the stale pre-payment one', function () {
    Notification::fake();

    $portalUser = app(ClientPortalUserService::class)->invite($this->client, ['name' => 'Portal User', 'email' => 'portal2@bank.example'])['user'];

    $invoice = $this->service->createInvoice(
        tenantId: $this->tenant->id, clientId: $this->client->id, assignmentId: null,
        items: [['description' => 'Valuation fee', 'quantity' => 1, 'unit_rate' => 100000]],
        vatPct: 0, tdsPct: 0, discountAmount: 0, dueDate: now()->addDays(15)->toDateString(), createdByUserId: null,
    );

    // total_amount is 100,000; pay 40,000 -- correct outstanding after this payment is 60,000.
    $this->service->recordPayment($invoice, 40000, 'bank_transfer', null, null, null);

    Notification::assertSentTo($portalUser, function (InvoiceEventNotification $notification, array $channels) {
        $array = $notification->toArray((object) ['preferred_locale' => 'en']);

        return str_contains($array['message'], '60,000.00') && ! str_contains($array['message'], '100,000.00 outstanding');
    });
});
