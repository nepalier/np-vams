<?php

declare(strict_types=1);

namespace App\Domain\Rates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Deliberately NOT using BelongsToTenant -- see the migration docblock:
 * government minimum rates are shared platform-level reference data.
 */
class GovernmentLandRate extends Model
{
    use HasUuids, LogsActivity;

    protected $fillable = [
        'fiscal_year_id', 'province_id', 'district_id', 'land_revenue_office', 'local_level_id', 'ward_id',
        'former_vdc', 'location', 'road', 'land_category', 'rate_unit_id', 'minimum_rate', 'effective_date',
        'source_document', 'source_page', 'verified_by_user_id', 'verified_at', 'approval_status',
        'version', 'superseded_by_id', 'is_current',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'verified_at' => 'datetime',
        'is_current' => 'boolean',
        'minimum_rate' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll(); // every version transition logged, nothing excluded
    }
}
