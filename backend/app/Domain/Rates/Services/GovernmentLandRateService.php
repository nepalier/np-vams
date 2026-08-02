<?php

declare(strict_types=1);

namespace App\Domain\Rates\Services;

use App\Domain\Rates\Models\GovernmentLandRate;
use Illuminate\Support\Facades\DB;

/**
 * Section 20: "Never overwrite historical fiscal-year rates." Every
 * mutation here creates a NEW row -- an existing rate is only ever
 * marked superseded (is_current=false, superseded_by_id set), never
 * updated or deleted. A report generated last fiscal year against a
 * now-outdated rate must still be able to show exactly what rate was in
 * effect at the time, which an in-place UPDATE would destroy.
 */
class GovernmentLandRateService
{
    public function create(array $data): GovernmentLandRate
    {
        return GovernmentLandRate::create([
            ...$data,
            'version' => 1,
            'is_current' => true,
        ]);
    }

    /**
     * Publishes a corrected/updated rate for the same location+fiscal
     * year, marking the old one superseded rather than touching it.
     */
    public function createNewVersion(GovernmentLandRate $existing, array $data): GovernmentLandRate
    {
        return DB::transaction(function () use ($existing, $data) {
            $newVersion = GovernmentLandRate::create([
                ...$data,
                'fiscal_year_id' => $existing->fiscal_year_id,
                'district_id' => $existing->district_id,
                'version' => $existing->version + 1,
                'is_current' => true,
            ]);

            $existing->forceFill(['is_current' => false, 'superseded_by_id' => $newVersion->id])->save();

            return $newVersion;
        });
    }

    /**
     * The lookup the WeightedLandRateEngine's UI actually needs: "what's
     * the current government rate for this location, this fiscal year."
     * Falls back progressively from the most specific match (ward) to
     * the least (district-only), since not every district has ward-level
     * rate data recorded.
     */
    public function findCurrentRate(int $fiscalYearId, int $districtId, ?int $localLevelId, ?int $wardId, ?string $landCategory): ?GovernmentLandRate
    {
        $query = GovernmentLandRate::where('fiscal_year_id', $fiscalYearId)
            ->where('district_id', $districtId)
            ->where('is_current', true)
            ->where('approval_status', 'approved');

        if ($landCategory !== null) {
            $query->where('land_category', $landCategory);
        }

        if ($wardId !== null) {
            $wardMatch = (clone $query)->where('ward_id', $wardId)->first();
            if ($wardMatch !== null) {
                return $wardMatch;
            }
        }

        if ($localLevelId !== null) {
            $localLevelMatch = (clone $query)->where('local_level_id', $localLevelId)->whereNull('ward_id')->first();
            if ($localLevelMatch !== null) {
                return $localLevelMatch;
            }
        }

        return $query->whereNull('local_level_id')->whereNull('ward_id')->first();
    }
}
