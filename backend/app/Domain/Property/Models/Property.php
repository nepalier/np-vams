<?php

declare(strict_types=1);

namespace App\Domain\Property\Models;

use App\Domain\Building\Models\Building;
use App\Domain\MasterData\Models\District;
use App\Domain\MasterData\Models\LocalLevel;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Property extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'property_code', 'property_name', 'property_type_id', 'property_subtype',
        'property_use', 'proposed_use', 'ownership_type', 'occupancy_status', 'address',
        'province_id', 'district_id', 'local_level_id', 'ward_id', 'tole', 'road_name', 'landmark',
        'latitude', 'longitude', 'elevation_m', 'survey_sheet_number', 'land_revenue_office', 'survey_office',
        'area_classification', 'distance_from_major_road_m', 'distance_from_market_m',
        'distance_from_school_m', 'distance_from_hospital_m', 'distance_from_public_transport_m',
        'nearby_infrastructure', 'location_description',
    ];

    public function parcels(): HasMany
    {
        return $this->hasMany(LandParcel::class);
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function district(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function localLevel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LocalLevel::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->logAll();
    }
}
