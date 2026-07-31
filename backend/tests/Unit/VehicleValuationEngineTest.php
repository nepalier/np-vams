<?php

use App\Domain\Valuation\Services\VehicleValuationEngine;

beforeEach(function () {
    $this->engine = new VehicleValuationEngine; // defaults: 10% scrap, 10%/annum depreciation, 2%/annum other cost
});

test('follows the exact line-by-line formula from the reference bank vehicle valuation format', function () {
    $result = $this->engine->calculate(currentMarketPriceOfNew: 3_000_000, ageYears: 4);

    // Line 1: 3,000,000. Line 2 (10% scrap): 300,000. Line 3 (Bankable = 1-2): 2,700,000.
    expect($result['scrap_deduction_amount'])->toBe(300_000.0);
    expect($result['bankable_value'])->toBe(2_700_000.0);

    // Line 4 (10%/annum straight-line on Bankable, 4 years): 2,700,000 * 0.10 * 4 = 1,080,000.
    // Line 5 (Net = 3-4): 2,700,000 - 1,080,000 = 1,620,000.
    expect($result['depreciation_amount'])->toBe(1_080_000.0);
    expect($result['net_value'])->toBe(1_620_000.0);

    // Line 7 (Other Costs, 2%/annum on Net, 4 years): 1,620,000 * 0.02 * 4 = 129,600.
    // Line 8 (Net FMV = 5-6-7, no other reducing factors): 1,620,000 - 0 - 129,600 = 1,490,400.
    expect($result['other_cost_amount'])->toBe(129_600.0);
    expect($result['net_fair_market_value'])->toBe(1_490_400.0);
});

test('other reducing factors (accident history, missing parts) are subtracted from the final value', function () {
    $result = $this->engine->calculate(currentMarketPriceOfNew: 1_000_000, ageYears: 1, otherReducingFactors: 50_000);

    $withoutReducing = $this->engine->calculate(currentMarketPriceOfNew: 1_000_000, ageYears: 1);

    expect($result['net_fair_market_value'])->toBe($withoutReducing['net_fair_market_value'] - 50_000);
});

test('a brand new vehicle (age 0) has zero depreciation and zero other cost', function () {
    $result = $this->engine->calculate(currentMarketPriceOfNew: 1_000_000, ageYears: 0);

    expect($result['depreciation_amount'])->toBe(0.0);
    expect($result['other_cost_amount'])->toBe(0.0);
    expect($result['net_fair_market_value'])->toBe($result['bankable_value']);
});

test('depreciation is line/straight basis, not compounded -- doubling the age exactly doubles the depreciation amount', function () {
    $twoYears = $this->engine->calculate(currentMarketPriceOfNew: 1_000_000, ageYears: 2);
    $fourYears = $this->engine->calculate(currentMarketPriceOfNew: 1_000_000, ageYears: 4);

    expect($fourYears['depreciation_amount'])->toBe($twoYears['depreciation_amount'] * 2);
});

test('net value never goes negative even for a very old vehicle with heavy depreciation', function () {
    $result = $this->engine->calculate(currentMarketPriceOfNew: 500_000, ageYears: 50); // depreciation would mathematically exceed bankable value

    expect($result['net_value'])->toBe(0.0);
    expect($result['net_fair_market_value'])->toBe(0.0);
});

test('a custom scrap/depreciation/other-cost convention (not the 10/10/2 default) is honoured', function () {
    $engine = new VehicleValuationEngine(scrapDeductionPct: 15.0, depreciationPctPerAnnum: 8.0, otherCostPctPerAnnum: 1.0);

    $result = $engine->calculate(currentMarketPriceOfNew: 1_000_000, ageYears: 1);

    expect($result['scrap_deduction_amount'])->toBe(150_000.0);
});

test('rejects a negative market price', function () {
    $this->engine->calculate(currentMarketPriceOfNew: -1, ageYears: 1);
})->throws(InvalidArgumentException::class);
