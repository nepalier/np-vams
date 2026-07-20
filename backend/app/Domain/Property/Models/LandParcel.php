<?php

declare(strict_types=1);

namespace App\Domain\Property\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LandParcel extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'property_id', 'kitta_number', 'survey_sheet_number', 'lalpurja_number',
        'former_vdc_or_municipality', 'local_level_id', 'ward_id', 'land_revenue_office', 'survey_office',
        'land_category', 'land_use_category',
        'area_lalpurja', 'area_lalpurja_unit_id', 'area_lalpurja_sqm',
        'area_cadastral', 'area_cadastral_unit_id', 'area_cadastral_sqm',
        'area_site_measured', 'area_site_measured_unit_id', 'area_site_measured_sqm',
        'area_considered_sqm', 'area_affected_road_widening_sqm', 'area_affected_setback_sqm',
        'area_affected_river_sqm', 'area_affected_transmission_line_sqm', 'area_encroached_sqm',
        'area_net_usable_sqm', 'acquisition_date', 'registration_deed_number', 'mortgage_status',
        'encumbrance_status', 'has_court_dispute', 'easement', 'right_of_way', 'lease_information',
        'four_boundaries', 'boundary_points', 'remarks',
    ];

    protected $casts = [
        'four_boundaries' => 'array',
        'boundary_points' => 'array',
        'has_court_dispute' => 'boolean',
        'acquisition_date' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->logAll();
    }
}
