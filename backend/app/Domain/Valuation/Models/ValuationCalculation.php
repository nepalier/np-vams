<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ValuationCalculation extends Model
{
    use BelongsToTenant, HasUuids, LogsActivity;

    protected $fillable = [
        'tenant_id', 'valuation_assignment_id', 'property_id', 'land_parcel_id', 'building_id',
        'method', 'status', 'input_snapshot', 'computed_value', 'computed_details',
        'calculated_by_user_id', 'calculated_at',
    ];

    protected $casts = [
        'input_snapshot' => 'array',
        'computed_details' => 'array',
        'calculated_at' => 'datetime',
        'computed_value' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ValuationCalculationItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll(); // financial calculation -- always logged, never "only dirty"
    }
}
