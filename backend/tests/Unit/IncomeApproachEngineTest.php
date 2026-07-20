<?php

use App\Domain\Valuation\Services\IncomeApproachEngine;

beforeEach(function () {
    $this->engine = new IncomeApproachEngine;
});

test('direct capitalization divides net operating income by the cap rate', function () {
    $result = $this->engine->directCapitalization([
        'monthly_rent' => 100000,
        'vacancy_allowance_pct' => 10,
        'operating_expenses_annual' => 120000,
        'capitalization_rate_pct' => 10,
    ]);

    // gross annual = 100000*12*0.9 = 1,080,000; NOI = 1,080,000 - 120,000 = 960,000
    // capital value = 960,000 / 0.10 = 9,600,000
    expect($result['gross_annual_income'])->toBe(1080000.0);
    expect($result['net_operating_income'])->toBe(960000.0);
    expect($result['capital_value'])->toBe(9600000.0);
});

test('rejects a zero or negative capitalization rate', function () {
    $this->engine->directCapitalization(['monthly_rent' => 1000, 'capitalization_rate_pct' => 0]);
})->throws(InvalidArgumentException::class);

test('capital value floors at zero when expenses exceed income', function () {
    $result = $this->engine->directCapitalization([
        'monthly_rent' => 1000,
        'operating_expenses_annual' => 100000,
        'capitalization_rate_pct' => 10,
    ]);

    expect($result['capital_value'])->toBe(0.0);
});

test('discounted cash flow discounts each year and adds a Gordon-growth terminal value', function () {
    $result = $this->engine->discountedCashFlow(
        annualNetOperatingIncomes: [100000, 100000, 100000],
        discountRatePct: 10,
        terminalGrowthRatePct: 2,
    );

    // Year 1 PV = 100000 / 1.1 = 90909.09
    expect($result['annual_present_values'][0]['present_value'])->toBe(90909.09);
    expect($result['capital_value'])->toBeGreaterThan($result['sum_present_value_of_cash_flows']);
});

test('rejects a terminal growth rate greater than or equal to the discount rate', function () {
    $this->engine->discountedCashFlow([100000], discountRatePct: 5, terminalGrowthRatePct: 5);
})->throws(InvalidArgumentException::class);
