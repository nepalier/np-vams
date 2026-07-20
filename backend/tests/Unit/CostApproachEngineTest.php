<?php

use App\Domain\Valuation\Services\CostApproachEngine;

beforeEach(function () {
    $this->engine = new CostApproachEngine;
});

test('replacement cost new applies all multiplicative factors plus additive costs', function () {
    $result = $this->engine->calculate([
        'built_up_area_sqm' => 100,
        'base_construction_rate' => 20000,
        'location_factor' => 1.1,
        'transportation_factor' => 1.0,
        'material_factor' => 1.0,
        'labour_factor' => 1.0,
        'professional_fee_pct' => 5,
        'external_works_amount' => 50000,
        'service_cost_amount' => 25000,
        'depreciation_method' => 'observed_condition',
        'physical_depreciation_pct' => 0,
    ]);

    // base = 100 * 20000 * 1.1 = 2,200,000; with 5% fee = 2,310,000; + 75,000 = 2,385,000
    expect($result['replacement_cost_new'])->toBe(2385000.0);
});

test('straight-line depreciation is proportional to age over economic life', function () {
    $result = $this->engine->calculate([
        'built_up_area_sqm' => 100,
        'base_construction_rate' => 10000, // RCN = 1,000,000
        'depreciation_method' => 'straight_line',
        'age_years' => 20,
        'economic_life_years' => 50, // 40% depreciation
    ]);

    expect($result['replacement_cost_new'])->toBe(1000000.0);
    expect($result['physical_depreciation_amount'])->toBe(400000.0);
    expect($result['depreciated_value'])->toBe(600000.0);
});

test('straight-line depreciation is capped at max_depreciation_pct even for very old buildings', function () {
    $result = $this->engine->calculate([
        'built_up_area_sqm' => 100,
        'base_construction_rate' => 10000, // RCN = 1,000,000
        'depreciation_method' => 'straight_line',
        'age_years' => 200,
        'economic_life_years' => 50, // ratio would be 400% without capping
        'max_depreciation_pct' => 80,
    ]);

    expect($result['physical_depreciation_amount'])->toBe(800000.0); // capped at 80%
});

test('component-wise depreciation sums each component independently', function () {
    $result = $this->engine->calculate([
        'built_up_area_sqm' => 100,
        'base_construction_rate' => 10000,
        'depreciation_method' => 'component_wise',
        'components' => [
            ['amount' => 400000, 'depreciation_pct' => 50], // 200,000
            ['amount' => 300000, 'depreciation_pct' => 20], // 60,000
            ['amount' => 300000, 'depreciation_pct' => 10], // 30,000
        ],
    ]);

    expect($result['physical_depreciation_amount'])->toBe(290000.0);
});

test('rejects negative functional obsolescence', function () {
    $this->engine->calculate([
        'built_up_area_sqm' => 100,
        'base_construction_rate' => 10000,
        'depreciation_method' => 'observed_condition',
        'physical_depreciation_pct' => 0,
        'functional_obsolescence_amount' => -1000,
    ]);
})->throws(InvalidArgumentException::class);

test('depreciated value never goes below zero even if depreciation exceeds replacement cost', function () {
    $result = $this->engine->calculate([
        'built_up_area_sqm' => 100,
        'base_construction_rate' => 10000, // RCN = 1,000,000
        'depreciation_method' => 'observed_condition',
        'physical_depreciation_pct' => 80,
        'functional_obsolescence_amount' => 500000,
        'economic_obsolescence_amount' => 500000,
    ]);

    expect($result['depreciated_value'])->toBe(0.0);
});
