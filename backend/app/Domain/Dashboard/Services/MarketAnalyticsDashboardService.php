<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Domain\Comparable\Models\ComparableProperty;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\Valuation\Models\ValuationReconciliation;
use Illuminate\Support\Facades\DB;

/**
 * Section 37 "Market analytics" dashboard. `government_land_rates` is
 * platform-level shared data (Phase 5 decision), so this reads it WITHOUT
 * tenant scoping by design -- market analytics comparing a tenant's own
 * comparable data against the shared government baseline only makes sense
 * if the baseline side of that comparison isn't artificially narrowed to
 * one tenant's rows (there wouldn't be any).
 */
class MarketAnalyticsDashboardService
{
    public function summary(): array
    {
        $currentFiscalYearId = FiscalYear::where('is_current', true)->value('id');

        return [
            'average_government_land_rate_by_district' => $this->averageGovernmentRateByDistrict($currentFiscalYearId),
            'average_comparable_unit_rate' => (float) (ComparableProperty::whereNotNull('unit_rate')->avg('unit_rate') ?? 0),
            'median_comparable_unit_rate' => $this->medianComparableUnitRate(),
            'comparable_reliability_distribution' => ComparableProperty::select('reliability_grade', DB::raw('count(*) as count'))
                ->groupBy('reliability_grade')
                ->pluck('count', 'reliability_grade')
                ->all(),
            'government_to_market_ratio' => $this->governmentToMarketRatio(),
            'property_type_distribution' => DB::table('properties')
                ->join('property_types', 'property_types.id', '=', 'properties.property_type_id')
                ->select('property_types.name_en', DB::raw('count(*) as count'))
                ->groupBy('property_types.name_en')
                ->pluck('count', 'name_en')
                ->all(),
        ];
    }

    private function averageGovernmentRateByDistrict(?int $fiscalYearId): array
    {
        if ($fiscalYearId === null) {
            return [];
        }

        return DB::table('government_land_rates')
            ->join('districts', 'districts.id', '=', 'government_land_rates.district_id')
            ->where('government_land_rates.fiscal_year_id', $fiscalYearId)
            ->where('government_land_rates.is_current', true)
            ->select('districts.name_en', DB::raw('avg(minimum_rate) as avg_rate'))
            ->groupBy('districts.name_en')
            ->pluck('avg_rate', 'name_en')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    private function medianComparableUnitRate(): ?float
    {
        $rates = ComparableProperty::whereNotNull('unit_rate')->orderBy('unit_rate')->pluck('unit_rate');

        if ($rates->isEmpty()) {
            return null;
        }

        $count = $rates->count();
        $middle = intdiv($count, 2);

        $median = $count % 2 === 0
            ? (((float) $rates[$middle - 1] + (float) $rates[$middle]) / 2)
            : (float) $rates[$middle];

        return round($median, 2);
    }

    /**
     * Average of (reconciled market value ÷ government minimum value)
     * across reconciliations that recorded both -- a real ratio computed
     * from the two figures that were actually reconciled together for the
     * same property, not two independently-averaged numbers divided
     * against each other (which would be a different, less meaningful
     * statistic).
     */
    private function governmentToMarketRatio(): ?float
    {
        $reconciliations = ValuationReconciliation::whereNotNull('government_minimum_value')
            ->where('government_minimum_value', '>', 0)
            ->get(['reconciled_market_value', 'government_minimum_value']);

        if ($reconciliations->isEmpty()) {
            return null;
        }

        $ratios = $reconciliations->map(
            fn ($r) => (float) $r->reconciled_market_value / (float) $r->government_minimum_value
        );

        return round((float) $ratios->avg(), 3);
    }
}
