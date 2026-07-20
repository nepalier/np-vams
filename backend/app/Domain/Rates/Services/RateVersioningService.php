<?php

declare(strict_types=1);

namespace App\Domain\Rates\Services;

use App\Domain\Rates\Models\ConstructionRate;
use App\Domain\Rates\Models\GovernmentLandRate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Shared "never overwrite" versioning behaviour for both rate registries
 * (Sections 20 & 27). Correcting a published rate always creates a brand
 * new row and marks the old one superseded -- an UPDATE that mutates
 * minimum_rate/base_rate directly on an existing row is never performed
 * anywhere in the codebase.
 */
class RateVersioningService
{
    public function reviseGovernmentRate(GovernmentLandRate $existing, array $changes): GovernmentLandRate
    {
        return DB::transaction(function () use ($existing, $changes) {
            $new = GovernmentLandRate::create(array_merge(
                $existing->only([
                    'fiscal_year_id', 'province_id', 'district_id', 'land_revenue_office', 'local_level_id',
                    'ward_id', 'former_vdc', 'location', 'road', 'land_category', 'rate_unit_id',
                    'minimum_rate', 'effective_date', 'source_document', 'source_page',
                ]),
                $changes,
                ['version' => $existing->version + 1, 'is_current' => true, 'approval_status' => 'pending']
            ));

            $existing->forceFill(['is_current' => false, 'superseded_by_id' => $new->id])->save();

            return $new;
        });
    }

    public function reviseConstructionRate(ConstructionRate $existing, array $changes): ConstructionRate
    {
        return DB::transaction(function () use ($existing, $changes) {
            $new = ConstructionRate::create(array_merge(
                $existing->only([
                    'tenant_id', 'fiscal_year_id', 'province_id', 'district_id', 'structural_type',
                    'building_type', 'construction_class', 'quality_grade', 'rate_unit_id', 'base_rate',
                    'material_adjustment_pct', 'labour_adjustment_pct', 'location_adjustment_pct',
                    'transportation_adjustment_pct', 'professional_fee_pct', 'external_works_amount',
                    'effective_date', 'source',
                ]),
                $changes,
                ['version' => $existing->version + 1, 'is_current' => true]
            ));

            $existing->forceFill(['is_current' => false, 'superseded_by_id' => $new->id])->save();

            return $new;
        });
    }
}
