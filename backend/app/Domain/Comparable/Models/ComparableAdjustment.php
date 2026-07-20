<?php

declare(strict_types=1);

namespace App\Domain\Comparable\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComparableAdjustment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'valuation_calculation_id', 'comparable_property_id', 'distance_from_subject_m',
        'weight', 'adjustment_factors', 'adjusted_unit_rate', 'justification',
    ];

    protected $casts = ['adjustment_factors' => 'array'];

    public function comparable(): BelongsTo
    {
        return $this->belongsTo(ComparableProperty::class, 'comparable_property_id');
    }
}
