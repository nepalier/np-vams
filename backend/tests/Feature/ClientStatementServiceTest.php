<?php

use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\ClientStatementService;
use App\Domain\Client\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->client = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Statement Bank', 'client_type' => 'commercial_bank']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

test('the statement running balance reflects invoices minus payments and credit notes in date order', function () {
    $billing = app(BillingService::class);

    $invoice = $billing->createInvoice(
        $this->tenant->id, $this->client->id, null,
        [['description' => 'Fee', 'quantity' => 1, 'unit_rate' => 100000]],
        0, 0, 0, null, $this->user->id,
    );

    $billing->recordPayment($invoice, 40000, 'bank_transfer', 'REF1', $this->user->id, null);
    $billing->issueCreditNote($invoice, 10000, 'Goodwill adjustment', $this->user->id);

    $statement = app(ClientStatementService::class)->generate($this->client->id);

    expect($statement['entries'])->toHaveCount(3); // invoice + payment + credit note
    expect($statement['closing_balance'])->toBe(50000.0); // 100000 - 40000 - 10000
    expect($statement['total_outstanding'])->toBe(50000.0);

    // Running balance decreases monotonically as credits are applied.
    $balances = array_column($statement['entries'], 'running_balance');
    expect($balances[0])->toBe(100000.0);
    expect(end($balances))->toBe(50000.0);
});

test('an empty statement for a client with no invoices returns a zeroed closing balance', function () {
    $statement = app(ClientStatementService::class)->generate($this->client->id);

    expect($statement['entries'])->toBeEmpty();
    expect($statement['closing_balance'])->toBe(0.0);
});
