<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tenant extends Model
{
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'status',
        'subscription_starts_at',
        'subscription_ends_at',
        'default_land_rate_government_weight_pct',
        'default_land_rate_market_weight_pct',
        'default_distress_value_pct',
        'default_vehicle_scrap_pct',
        'default_vehicle_depreciation_pct_per_annum',
        'default_vehicle_other_cost_pct_per_annum',
        'default_building_sanitary_fixture_pct',
        'default_building_electrical_fixture_pct',
        'default_building_depreciation_pct_per_annum',
    ];

    protected $casts = [
        'subscription_starts_at' => 'date',
        'subscription_ends_at' => 'date',
    ];

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->logAll();
    }
}
