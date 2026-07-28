<?php

declare(strict_types=1);

namespace App\Domain\Property\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandParcelPlanning extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'land_parcel_planning';

    protected $fillable = [
        'tenant_id', 'land_parcel_id', 'existing_land_use', 'government_land_use_category', 'zoning_category',
        'is_residential_zone', 'is_commercial_zone', 'is_industrial_zone', 'is_agricultural_zone',
        'is_forest_zone', 'is_conservation_zone', 'is_heritage_zone', 'has_airport_restriction',
        'road_setback_m', 'river_setback_m', 'right_of_way', 'max_building_coverage_pct', 'floor_area_ratio',
        'max_height_m', 'municipal_restrictions', 'has_acquisition_notice', 'has_road_expansion_notice',
        'proposed_infrastructure', 'building_regulation_reference', 'compliance_status', 'remarks',
    ];

    protected $casts = [
        'is_residential_zone' => 'boolean', 'is_commercial_zone' => 'boolean', 'is_industrial_zone' => 'boolean',
        'is_agricultural_zone' => 'boolean', 'is_forest_zone' => 'boolean', 'is_conservation_zone' => 'boolean',
        'is_heritage_zone' => 'boolean', 'has_airport_restriction' => 'boolean',
        'has_acquisition_notice' => 'boolean', 'has_road_expansion_notice' => 'boolean',
    ];

    public function landParcel(): BelongsTo
    {
        return $this->belongsTo(LandParcel::class);
    }
}
