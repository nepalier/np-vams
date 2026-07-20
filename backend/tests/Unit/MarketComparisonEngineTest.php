<?php

use App\Domain\Valuation\Services\MarketComparisonEngine;

beforeEach(function () {
    $this->engine = new MarketComparisonEngine;
});

test('adjusted rate is base rate times the product of all factors', function () {
    $result = $this->engine->calculate([
        ['base_rate' => 100000, 'factors' => ['time' => 1.05, 'location' => 0.95]],
    ]);

    // 100000 * 1.05 * 0.95 = 99750
    expect($result['per_comparable'][0]['adjusted_rate'])->toBe(99750.0);
});

test('mean, median, and weighted average are computed correctly across multiple comparables', function () {
    $result = $this->engine->calculate([
        ['base_rate' => 100000, 'weight' => 1, 'factors' => ['f' => 1.0]], // adjusted 100000
        ['base_rate' => 120000, 'weight' => 2, 'factors' => ['f' => 1.0]], // adjusted 120000
        ['base_rate' => 110000, 'weight' => 1, 'factors' => ['f' => 1.0]], // adjusted 110000
    ]);

    expect($result['mean'])->toBe(110000.0);
    expect($result['median'])->toBe(110000.0);
    // weighted avg = (100000*1 + 120000*2 + 110000*1) / 4 = 450000/4 = 112500
    expect($result['weighted_average'])->toBe(112500.0);
    expect($result['suggested_adopted_rate'])->toBe(112500.0);
});

test('a comparable far outside the group is flagged as an outlier', function () {
    $result = $this->engine->calculate([
        ['base_rate' => 100000, 'factors' => ['f' => 1.0]],
        ['base_rate' => 102000, 'factors' => ['f' => 1.0]],
        ['base_rate' => 98000, 'factors' => ['f' => 1.0]],
        ['base_rate' => 500000, 'factors' => ['f' => 1.0]], // clear outlier
    ], outlierStdDevThreshold: 1.0);

    expect($result['outlier_indices'])->toContain(3);
    expect($result['outlier_indices'])->not->toContain(0);
});

test('rejects a negative base rate', function () {
    $this->engine->calculate([
        ['base_rate' => -100, 'factors' => ['f' => 1.0]],
    ]);
})->throws(InvalidArgumentException::class);

test('rejects an empty comparable set', function () {
    $this->engine->calculate([]);
})->throws(InvalidArgumentException::class);
