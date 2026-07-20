<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Risk\Models\RiskIndicator;
use App\Domain\Risk\Models\RiskScoreBand;
use Illuminate\Database\Seeder;

/**
 * Default risk indicator catalogue (Section 29) and score bands, seeded
 * PER TENANT (both tables are tenant-scoped so each firm can tune weights
 * to their own risk appetite). Call this once per new tenant during
 * onboarding -- see TenantDemoSeeder for the pattern -- not as a one-time
 * global seeder.
 */
class DefaultRiskConfigSeeder extends Seeder
{
    private const INDICATORS = [
        ['no_road_access', 'No Road Access', 8],
        ['non_motorable_access', 'Non-Motorable Access', 4],
        ['ownership_dispute', 'Ownership Dispute', 10],
        ['unclear_boundary', 'Unclear Boundary', 6],
        ['area_mismatch', 'Area Mismatch', 5],
        ['unauthorized_construction', 'Unauthorized Construction', 7],
        ['incomplete_construction', 'Incomplete Construction', 4],
        ['flood_exposure', 'Flood Exposure', 6],
        ['landslide_exposure', 'Landslide Exposure', 8],
        ['river_proximity', 'River Proximity', 4],
        ['high_tension_line', 'High-Tension Line Proximity', 5],
        ['tenant_occupation', 'Tenant Occupation', 3],
        ['shared_access', 'Shared Access', 3],
        ['restricted_land_use', 'Restricted Land Use', 6],
        ['public_acquisition_notice', 'Public Acquisition Notice', 9],
        ['poor_marketability', 'Poor Marketability', 5],
        ['high_govt_market_diff', 'High Government-Market Value Difference', 4],
        ['missing_document', 'Missing Document', 6],
        ['mortgage_or_encumbrance', 'Mortgage or Encumbrance', 7],
        ['court_case', 'Court Case', 10],
        ['heritage_restriction', 'Heritage Restriction', 5],
        ['structural_defect', 'Structural Defect', 6],
    ];

    private const SCORE_BANDS = [
        [0, 10, 'low'],
        [10.01, 25, 'moderate'],
        [25.01, 45, 'high'],
        [45.01, 9999, 'unacceptable'],
    ];

    public function run(): void
    {
        $tenantId = app('currentTenantId');

        foreach (self::INDICATORS as [$code, $label, $weight]) {
            RiskIndicator::updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                ['label_en' => $label, 'default_weight' => $weight, 'is_active' => true]
            );
        }

        foreach (self::SCORE_BANDS as [$min, $max, $category]) {
            RiskScoreBand::updateOrCreate(
                ['tenant_id' => $tenantId, 'min_score' => $min, 'max_score' => $max],
                ['category' => $category]
            );
        }
    }
}
