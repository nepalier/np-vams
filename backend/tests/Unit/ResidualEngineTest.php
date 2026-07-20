<?php

use App\Domain\Valuation\Services\ResidualEngine;

beforeEach(function () {
    $this->engine = new ResidualEngine;
});

test('residual land value subtracts every cost line from gross development value', function () {
    $result = $this->engine->calculate([
        'gross_development_value' => 10000000,
        'construction_cost' => 5000000,
        'infrastructure_cost' => 500000,
        'approval_cost' => 200000,
        'professional_fee' => 300000,
        'financing_cost' => 250000,
        'marketing_cost' => 150000,
        'contingency' => 100000,
        'developer_profit' => 1500000,
    ]);

    expect($result['total_costs'])->toBe(8000000.0);
    expect($result['residual_land_value'])->toBe(2000000.0);
});

test('a negative residual value is returned, not floored at zero', function () {
    $result = $this->engine->calculate([
        'gross_development_value' => 1000000,
        'construction_cost' => 2000000,
        'developer_profit' => 100000,
    ]);

    expect($result['residual_land_value'])->toBeLessThan(0);
});

test('rejects negative gross development value', function () {
    $this->engine->calculate(['gross_development_value' => -1, 'construction_cost' => 0, 'developer_profit' => 0]);
})->throws(InvalidArgumentException::class);
