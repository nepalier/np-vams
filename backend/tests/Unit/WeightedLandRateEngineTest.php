<?php

use App\Domain\Valuation\Services\WeightedLandRateEngine;

beforeEach(function () {
    $this->engine = new WeightedLandRateEngine; // default 30% government / 70% market
});

/**
 * These exact figures are taken from the real Jyoti Bikash Bank Limited
 * valuation report used as the reference for this engine (client: Mr.
 * Dhruba Chaulagain, Hetauda, plots 5415 & 5416) -- not invented test
 * data. The document's own "Total Value of Land: 3,280,055.00" is the
 * verification oracle here, not a number this codebase produced.
 */
test('matches the real JBBL reference document\'s two-plot land valuation exactly', function () {
    $result = $this->engine->calculate([
        ['plot_label' => 'Front (5415 & 5416)', 'area_considered' => 0.472, 'government_rate' => 2_300_000, 'market_rate' => 6_500_000],
        ['plot_label' => 'Rear', 'area_considered' => 0.347, 'government_rate' => 750_000, 'market_rate' => 3_000_000],
    ]);

    expect($result['plots'][0]['weighted_rate'])->toBe(5_240_000.0);
    expect($result['plots'][0]['plot_value'])->toBe(2_473_280.0);
    expect($result['plots'][1]['weighted_rate'])->toBe(2_325_000.0);
    expect($result['plots'][1]['plot_value'])->toBe(806_775.0);
    expect($result['total_land_value'])->toBe(3_280_055.0);
});

test('a custom government/market split (not the 30/70 default) is honoured, not overridden by a hard-coded convention', function () {
    $engine = new WeightedLandRateEngine(governmentWeightPct: 50.0, marketWeightPct: 50.0);

    $result = $engine->calculate([
        ['plot_label' => 'Test', 'area_considered' => 1, 'government_rate' => 1_000_000, 'market_rate' => 2_000_000],
    ]);

    expect($result['plots'][0]['weighted_rate'])->toBe(1_500_000.0); // (1M+2M)/2
});

test('weights that do not sum to 100 are rejected at construction, not silently normalized', function () {
    new WeightedLandRateEngine(governmentWeightPct: 40.0, marketWeightPct: 40.0);
})->throws(InvalidArgumentException::class);

test('rejects an empty plot list', function () {
    $this->engine->calculate([]);
})->throws(InvalidArgumentException::class);

test('rejects a negative rate', function () {
    $this->engine->calculate([
        ['plot_label' => 'X', 'area_considered' => 1, 'government_rate' => -1, 'market_rate' => 1],
    ]);
})->throws(InvalidArgumentException::class);
