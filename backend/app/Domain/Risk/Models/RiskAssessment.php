<?php

declare(strict_types=1);

namespace App\Domain\Risk\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RiskAssessment extends Model
{
    use BelongsToTenant, HasUuids, LogsActivity;

    protected $fillable = [
        'tenant_id', 'valuation_assignment_id', 'property_id', 'computed_score', 'computed_category',
        'final_category', 'is_overridden', 'override_justification', 'assessed_by_user_id',
    ];

    protected $casts = ['computed_score' => 'decimal:2', 'is_overridden' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(RiskAssessmentItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
