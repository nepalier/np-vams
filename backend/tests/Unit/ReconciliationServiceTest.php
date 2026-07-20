<?php

use App\Domain\Valuation\Services\ReconciliationService;

beforeEach(function () {
    $this->service = new ReconciliationService;
});

test('reconciles a weighted value across multiple methods using reliability as default weight', function () {
    $result = $this->service->reconcile([
        ['method' => 'market_comparison', 'value' => 10000000, 'reliability_rating' => 4],
        ['method' => 'cost_approach', 'value' => 9000000, 'reliability_rating' => 2],
    ], roundingUnit: 0);

    // (10,000,000*4 + 9,000,000*2) / 6 = 58,000,000/6 = 9,666,666.67
    expect($result['computed_weighted_value'])->toBe(9666666.67);
    expect($result['reconciled_market_value'])->toBe(9666666.67);
});

test('rounds the reconciled value to the configured rounding unit', function () {
    $result = $this->service->reconcile([
        ['method' => 'market_comparison', 'value' => 9666666, 'reliability_rating' => 1],
    ], roundingUnit: 1000);

    expect($result['rounded_market_value'])->toBe(9667000.0);
});

test('a manual override requires a justification', function () {
    $this->service->reconcile(
        [['method' => 'market_comparison', 'value' => 1000000, 'reliability_rating' => 1]],
        manualOverrideValue: 1200000,
    );
})->throws(InvalidArgumentException::class);

test('a justified manual override replaces the computed value', function () {
    $result = $this->service->reconcile(
        [['method' => 'market_comparison', 'value' => 1000000, 'reliability_rating' => 1]],
        roundingUnit: 0,
        manualOverrideValue: 1200000,
        overrideJustification: 'Recent comparable sale not yet reflected in the database.',
    );

    expect($result['is_manual_override'])->toBeTrue();
    expect($result['reconciled_market_value'])->toBe(1200000.0);
    expect($result['computed_weighted_value'])->toBe(1000000.0); // original computation preserved for audit
});

test('derives dependent values from caller-supplied, not hard-coded, percentages', function () {
    $result = $this->service->deriveDependentValues(1000000, [
        'distress_pct' => 80,
        'forced_sale_pct' => 70,
        'mortgage_haircut_pct' => 25,
        'insurance_pct' => 90,
    ]);

    expect($result['distressValue'])->toBe(800000.0);
    expect($result['forcedSaleValue'])->toBe(700000.0);
    expect($result['mortgageValue'])->toBe(750000.0);
    expect($result['insuranceValue'])->toBe(900000.0);
});
