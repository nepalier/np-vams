<?php

declare(strict_types=1);

namespace App\Domain\Building\Models;

use App\Domain\Property\Models\Property;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Building extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'property_id', 'building_name', 'building_type', 'block_name',
        'number_of_floors', 'basement_floors', 'construction_year_bs', 'completion_year_bs',
        'building_age_years', 'current_use', 'approved_use', 'occupancy', 'building_permit_number',
        'drawing_approval_date', 'completion_certificate_number', 'house_tax_number',
        'has_earthquake_damage', 'retrofitting_status', 'seismic_vulnerability',
        'remaining_economic_life_years', 'overall_condition', 'structural_system',
        'foundation_type', 'roof_type', 'construction_details',
    ];

    protected $casts = [
        'has_earthquake_damage' => 'boolean',
        'drawing_approval_date' => 'date',
        'construction_details' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function floors(): HasMany
    {
        return $this->hasMany(BuildingFloor::class)->orderBy('floor_number');
    }

    public function conditionAssessments(): HasMany
    {
        return $this->hasMany(BuildingConditionAssessment::class)->orderByDesc('assessed_at');
    }

    public function latestConditionAssessment(): HasOne
    {
        return $this->hasOne(BuildingConditionAssessment::class)->latestOfMany('assessed_at');
    }
}
