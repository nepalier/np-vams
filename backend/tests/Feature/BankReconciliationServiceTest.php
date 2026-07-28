<?php

use App\Domain\Billing\Models\BankStatementLine;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\BankReconciliationService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = app(BankReconciliationService::class);
});

function makeInvoiceAndPayment(string $tenantId, float $amount, string $date): Payment
{
    $client = \App\Domain\Client\Models\Client::create(['tenant_id' => $tenantId, 'name_en' => 'Bank', 'client_type' => 'commercial_bank']);
    $fiscalYear = \App\Domain\MasterData\Models\FiscalYear::where('is_current', true)->first();

    $invoice = Invoice::create([
        'tenant_id' => $tenantId, 'client_id' => $client->id, 'invoice_number' => 'INV-TEST-'.uniqid(),
        'fiscal_year_id' => $fiscalYear->id, 'issue_date' => $date, 'total_amount' => $amount, 'status' => 'issued',
    ]);

    return Payment::create([
        'tenant_id' => $tenantId, 'invoice_id' => $invoice->id, 'payment_date' => $date,
        'amount' => $amount, 'payment_method' => 'bank_transfer',
    ]);
}

test('an unambiguous single matching payment is auto-matched', function () {
    $payment = makeInvoiceAndPayment($this->tenant->id, 50000, '2026-07-15');

    $result = $this->service->import([
        ['transaction_date' => '2026-07-15', 'description' => 'Transfer', 'reference_number' => null, 'amount' => 50000],
    ], $this->tenant->id, $this->user->id);

    expect($result['imported_count'])->toBe(1);

    $line = BankStatementLine::where('import_batch_id', $result['batch_id'])->first();
    expect($line->is_matched)->toBeTrue();
    expect($line->matched_payment_id)->toBe($payment->id);
    expect($line->match_method)->toBe('auto');
});

test('two equally-plausible candidates are left unmatched rather than guessed at', function () {
    makeInvoiceAndPayment($this->tenant->id, 25000, '2026-07-15');
    makeInvoiceAndPayment($this->tenant->id, 25000, '2026-07-16'); // same amount, close date -- genuinely ambiguous

    $result = $this->service->import([
        ['transaction_date' => '2026-07-15', 'description' => 'Transfer', 'reference_number' => null, 'amount' => 25000],
    ], $this->tenant->id, $this->user->id);

    $line = BankStatementLine::where('import_batch_id', $result['batch_id'])->first();
    expect($line->is_matched)->toBeFalse();
});

test('a statement line with no matching payment at all stays unmatched', function () {
    $result = $this->service->import([
        ['transaction_date' => '2026-07-15', 'description' => 'Unknown transfer', 'reference_number' => null, 'amount' => 99999],
    ], $this->tenant->id, $this->user->id);

    $line = BankStatementLine::where('import_batch_id', $result['batch_id'])->first();
    expect($line->is_matched)->toBeFalse();

    $summary = $this->service->unmatchedSummary($this->tenant->id);
    expect($summary['unmatched_statement_lines'])->toBe(1);
});

test('a payment already matched to one statement line is not offered as a candidate for a second line', function () {
    makeInvoiceAndPayment($this->tenant->id, 30000, '2026-07-15');

    $firstImport = $this->service->import([
        ['transaction_date' => '2026-07-15', 'description' => 'A', 'reference_number' => null, 'amount' => 30000],
    ], $this->tenant->id, $this->user->id);

    $secondImport = $this->service->import([
        ['transaction_date' => '2026-07-15', 'description' => 'B (duplicate amount)', 'reference_number' => null, 'amount' => 30000],
    ], $this->tenant->id, $this->user->id);

    $secondLine = BankStatementLine::where('import_batch_id', $secondImport['batch_id'])->first();
    expect($secondLine->is_matched)->toBeFalse(); // the only real payment was already claimed by the first line
});

test('manual matching rejects an amount mismatch', function () {
    $payment = makeInvoiceAndPayment($this->tenant->id, 10000, '2026-07-15');

    $line = BankStatementLine::create([
        'tenant_id' => $this->tenant->id, 'transaction_date' => '2026-07-15', 'amount' => 9999,
        'import_batch_id' => (string) \Illuminate\Support\Str::uuid(),
    ]);

    $this->service->matchManually($line, $payment);
})->throws(RuntimeException::class);
