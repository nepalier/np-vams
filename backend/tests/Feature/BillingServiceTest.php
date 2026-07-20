<?php

use App\Domain\Billing\Services\BillingService;
use App\Domain\Client\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->client = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Test Bank', 'client_type' => 'commercial_bank']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = app(BillingService::class);
});

test('creating an invoice computes totals and assigns a sequential tenant-scoped number', function () {
    $invoice = $this->service->createInvoice(
        tenantId: $this->tenant->id,
        clientId: $this->client->id,
        assignmentId: null,
        items: [['description' => 'Valuation fee', 'quantity' => 1, 'unit_rate' => 50000]],
        vatPct: 13,
        tdsPct: 1.5,
        discountAmount: 0,
        dueDate: null,
        createdByUserId: $this->user->id,
    );

    expect($invoice->invoice_number)->toBe('INV-2082-000001');
    expect($invoice->total_amount)->toBe('56500.00'); // 50000 + 13% VAT
    expect($invoice->outstanding_amount)->toBe('55750.00'); // total - TDS (750)
    expect($invoice->status)->toBe('issued');
});

test('recording a payment reduces outstanding and updates status', function () {
    $invoice = $this->service->createInvoice(
        $this->tenant->id, $this->client->id, null,
        [['description' => 'Fee', 'quantity' => 1, 'unit_rate' => 10000]],
        0, 0, 0, null, $this->user->id,
    );

    $this->service->recordPayment($invoice, 10000, 'bank_transfer', 'TXN123', $this->user->id, null);

    expect($invoice->fresh()->status)->toBe('paid');
    expect($invoice->fresh()->outstanding_amount)->toBe('0.00');
});

test('a payment larger than the outstanding balance is rejected', function () {
    $invoice = $this->service->createInvoice(
        $this->tenant->id, $this->client->id, null,
        [['description' => 'Fee', 'quantity' => 1, 'unit_rate' => 10000]],
        0, 0, 0, null, $this->user->id,
    );

    $this->service->recordPayment($invoice, 999999, 'cash', null, $this->user->id, null);
})->throws(RuntimeException::class);

test('a partial payment leaves the invoice partially paid', function () {
    $invoice = $this->service->createInvoice(
        $this->tenant->id, $this->client->id, null,
        [['description' => 'Fee', 'quantity' => 1, 'unit_rate' => 20000]],
        0, 0, 0, null, $this->user->id,
    );

    $this->service->recordPayment($invoice, 8000, 'cash', null, $this->user->id, null);

    expect($invoice->fresh()->status)->toBe('partially_paid');
    expect($invoice->fresh()->outstanding_amount)->toBe('12000.00');
});

test('a credit note reduces outstanding just like a payment', function () {
    $invoice = $this->service->createInvoice(
        $this->tenant->id, $this->client->id, null,
        [['description' => 'Fee', 'quantity' => 1, 'unit_rate' => 15000]],
        0, 0, 0, null, $this->user->id,
    );

    $this->service->issueCreditNote($invoice, 15000, 'Duplicate billing, correcting.', $this->user->id);

    expect($invoice->fresh()->status)->toBe('paid');
    expect($invoice->fresh()->credited_amount)->toBe('15000.00');
});

test('invoice numbering is sequential per tenant', function () {
    $first = $this->service->createInvoice($this->tenant->id, $this->client->id, null, [['description' => 'A', 'quantity' => 1, 'unit_rate' => 1000]], 0, 0, 0, null, $this->user->id);
    $second = $this->service->createInvoice($this->tenant->id, $this->client->id, null, [['description' => 'B', 'quantity' => 1, 'unit_rate' => 1000]], 0, 0, 0, null, $this->user->id);

    expect($first->invoice_number)->toBe('INV-2082-000001');
    expect($second->invoice_number)->toBe('INV-2082-000002');
});
