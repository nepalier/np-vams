<?php

declare(strict_types=1);

namespace App\Domain\Rates\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ConstructionRate extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'fiscal_year_id', 'province_id', 'district_id', 'structural_type', 'building_type',
        'construction_class', 'quality_grade', 'rate_unit_id', 'base_rate', 'material_adjustment_pct',
        'labour_adjustment_pct', 'location_adjustment_pct', 'transportation_adjustment_pct',
        'professional_fee_pct', 'external_works_amount', 'effective_date', 'source',
        'approved_by_user_id', 'version', 'superseded_by_id', 'is_current',
    ];

    protected $casts = ['effective_date' => 'date', 'is_current' => 'boolean'];
}
