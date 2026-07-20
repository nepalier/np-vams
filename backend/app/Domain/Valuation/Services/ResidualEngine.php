<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Services;

use InvalidArgumentException;

/**
 * Section 25 (Development/Residual Method):
 * residual land value = gross development value
 *                        - construction cost - infrastructure cost - approval cost
 *                        - professional fee - financing cost - marketing cost
 *                        - contingency - developer profit
 *
 * Developer profit and financing cost can each be supplied either as a
 * direct amount or as a percentage of GDV/cost -- the caller resolves which
 * one it has and passes an amount either way, so this engine has exactly
 * one calculation path and no hidden "if it's a percentage, guess how to
 * apply it" branching.
 */
class ResidualEngine
{
    /**
     * @param  array{
     *   gross_development_value: float, construction_cost: float,
     *   infrastructure_cost?: float, approval_cost?: float,
     *   professional_fee?: float, financing_cost?: float,
     *   marketing_cost?: float, contingency?: float, developer_profit: float,
     * }  $input
     */
    public function calculate(array $input): array
    {
        $gdv = $input['gross_development_value'];

        if ($gdv < 0 || $input['construction_cost'] < 0 || $input['developer_profit'] < 0) {
            throw new InvalidArgumentException('gross_development_value, construction_cost, and developer_profit must be non-negative.');
        }

        $costs = [
            'construction_cost' => $input['construction_cost'],
            'infrastructure_cost' => $input['infrastructure_cost'] ?? 0.0,
            'approval_cost' => $input['approval_cost'] ?? 0.0,
            'professional_fee' => $input['professional_fee'] ?? 0.0,
            'financing_cost' => $input['financing_cost'] ?? 0.0,
            'marketing_cost' => $input['marketing_cost'] ?? 0.0,
            'contingency' => $input['contingency'] ?? 0.0,
            'developer_profit' => $input['developer_profit'],
        ];

        $totalCosts = round(array_sum($costs), 2);
        $residualLandValue = round($gdv - $totalCosts, 2);

        return [
            'gross_development_value' => $gdv,
            'cost_breakdown' => $costs,
            'total_costs' => $totalCosts,
            // Deliberately NOT floored at zero: a negative residual is a
            // real and important signal (the scheme doesn't stack up at
            // this land price) that a valuer needs to see, not hide.
            'residual_land_value' => $residualLandValue,
        ];
    }
}
