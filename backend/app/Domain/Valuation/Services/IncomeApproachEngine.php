<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Services;

use InvalidArgumentException;

/**
 * Section 24: "Capital value = Net operating income ÷ capitalization rate",
 * plus an optional discounted cash flow calculation.
 */
class IncomeApproachEngine
{
    /**
     * @param  array{
     *   monthly_rent: float, vacancy_allowance_pct?: float,
     *   operating_expenses_annual?: float, property_tax_annual?: float,
     *   insurance_annual?: float, maintenance_annual?: float,
     *   management_cost_annual?: float, capitalization_rate_pct: float,
     * }  $input
     */
    public function directCapitalization(array $input): array
    {
        $capRate = $input['capitalization_rate_pct'];

        if ($capRate <= 0) {
            throw new InvalidArgumentException('capitalization_rate_pct must be positive.');
        }

        $vacancyPct = $input['vacancy_allowance_pct'] ?? 0.0;
        $grossAnnualIncome = $input['monthly_rent'] * 12 * (1 - $vacancyPct / 100);

        $totalExpenses = ($input['operating_expenses_annual'] ?? 0)
            + ($input['property_tax_annual'] ?? 0)
            + ($input['insurance_annual'] ?? 0)
            + ($input['maintenance_annual'] ?? 0)
            + ($input['management_cost_annual'] ?? 0);

        $netOperatingIncome = round($grossAnnualIncome - $totalExpenses, 2);
        $capitalValue = round($netOperatingIncome / ($capRate / 100), 2);

        return [
            'gross_annual_income' => round($grossAnnualIncome, 2),
            'total_operating_expenses' => round($totalExpenses, 2),
            'net_operating_income' => $netOperatingIncome,
            'capitalization_rate_pct' => $capRate,
            'capital_value' => max(0.0, $capitalValue),
        ];
    }

    /**
     * @param  array<int, float>  $annualNetOperatingIncomes  one entry per year of the holding period
     * @param  float  $discountRatePct
     * @param  float  $terminalGrowthRatePct  used only to derive the terminal value at the end of the holding period
     */
    public function discountedCashFlow(array $annualNetOperatingIncomes, float $discountRatePct, float $terminalGrowthRatePct = 0.0): array
    {
        if (count($annualNetOperatingIncomes) === 0) {
            throw new InvalidArgumentException('At least one year of NOI is required.');
        }

        if ($discountRatePct <= 0) {
            throw new InvalidArgumentException('discountRatePct must be positive.');
        }

        $discountRate = $discountRatePct / 100;
        $presentValues = [];
        $sumPresentValue = 0.0;

        foreach (array_values($annualNetOperatingIncomes) as $index => $noi) {
            $year = $index + 1;
            $pv = round($noi / (1 + $discountRate) ** $year, 2);
            $presentValues[] = ['year' => $year, 'noi' => $noi, 'present_value' => $pv];
            $sumPresentValue += $pv;
        }

        $terminalGrowth = $terminalGrowthRatePct / 100;

        if ($terminalGrowth >= $discountRate) {
            throw new InvalidArgumentException('terminalGrowthRatePct must be less than discountRatePct for the Gordon growth terminal value to converge.');
        }

        $lastYearNoi = end($annualNetOperatingIncomes);
        $holdingYears = count($annualNetOperatingIncomes);
        $terminalValue = ($lastYearNoi * (1 + $terminalGrowth)) / ($discountRate - $terminalGrowth);
        $presentTerminalValue = round($terminalValue / (1 + $discountRate) ** $holdingYears, 2);

        return [
            'annual_present_values' => $presentValues,
            'sum_present_value_of_cash_flows' => round($sumPresentValue, 2),
            'terminal_value' => round($terminalValue, 2),
            'present_value_of_terminal_value' => $presentTerminalValue,
            'capital_value' => round($sumPresentValue + $presentTerminalValue, 2),
        ];
    }
}
