<?php

declare(strict_types=1);

namespace App\Domain\Building\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BuildingConditionAssessment extends Model
{
    use BelongsToTenant, HasUuids, LogsActivity;

    protected $fillable = [
        'tenant_id', 'building_id', 'assessed_by_user_id', 'assessed_at', 'structural_risk',
        'required_repairs', 'repair_cost_estimate', 'remaining_life_years', 'overall_rating', 'remarks',
    ];

    protected $casts = ['assessed_at' => 'datetime'];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BuildingConditionAssessmentItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
