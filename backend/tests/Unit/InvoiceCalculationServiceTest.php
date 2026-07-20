<?php

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\InvoiceCalculationService;

beforeEach(function () {
    $this->service = new InvoiceCalculationService;
});

test('subtotal is the sum of quantity times unit rate across all line items', function () {
    $totals = $this->service->computeTotals([
        ['quantity' => 1, 'unit_rate' => 50000],
        ['quantity' => 2, 'unit_rate' => 5000],
    ], vatPct: 0, tdsPct: 0);

    expect($totals['subtotal'])->toBe(60000.0);
});

test('VAT is added on top and TDS is computed separately from the same subtotal', function () {
    $totals = $this->service->computeTotals([
        ['quantity' => 1, 'unit_rate' => 100000],
    ], vatPct: 13, tdsPct: 1.5);

    expect($totals['vat_amount'])->toBe(13000.0);
    expect($totals['tds_amount'])->toBe(1500.0);
    expect($totals['total_amount'])->toBe(113000.0); // subtotal + VAT, TDS does not reduce the invoice total
});

test('discount reduces the total amount', function () {
    $totals = $this->service->computeTotals([
        ['quantity' => 1, 'unit_rate' => 100000],
    ], vatPct: 13, tdsPct: 0, discountAmount: 5000);

    expect($totals['total_amount'])->toBe(108000.0); // 100000 + 13000 - 5000
});

test('rejects a discount larger than subtotal plus VAT', function () {
    $this->service->computeTotals([['quantity' => 1, 'unit_rate' => 1000]], vatPct: 0, tdsPct: 0, discountAmount: 5000);
})->throws(InvalidArgumentException::class);

test('outstanding amount subtracts TDS, payments, and credits from the total', function () {
    $invoice = new Invoice([
        'total_amount' => 113000,
        'tds_amount' => 1500,
        'paid_amount' => 50000,
        'credited_amount' => 10000,
    ]);

    $outstanding = $this->service->recalculateOutstanding($invoice);

    expect($outstanding)->toBe(51500.0); // 113000 - 1500 - 50000 - 10000
});

test('outstanding never goes negative even if overpaid', function () {
    $invoice = new Invoice(['total_amount' => 100000, 'tds_amount' => 0, 'paid_amount' => 150000, 'credited_amount' => 0]);

    expect($this->service->recalculateOutstanding($invoice))->toBe(0.0);
});

test('an invoice with zero outstanding resolves to paid status', function () {
    $invoice = new Invoice(['status' => 'issued', 'total_amount' => 100000, 'tds_amount' => 0, 'paid_amount' => 100000, 'credited_amount' => 0, 'due_date' => null]);

    expect($this->service->resolveStatus($invoice, 0.0))->toBe('paid');
});

test('a cancelled invoice stays cancelled regardless of outstanding balance', function () {
    $invoice = new Invoice(['status' => 'cancelled', 'due_date' => null]);

    expect($this->service->resolveStatus($invoice, 50000.0))->toBe('cancelled');
});
