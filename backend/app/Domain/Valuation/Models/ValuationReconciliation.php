<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ValuationReconciliation extends Model
{
    use BelongsToTenant, HasUuids, LogsActivity;

    protected $fillable = [
        'tenant_id', 'valuation_assignment_id', 'property_id', 'method_inputs', 'reconciled_market_value',
        'rounded_market_value', 'government_land_rate_id', 'government_minimum_value', 'distress_value',
        'forced_sale_value', 'mortgage_value', 'insurance_value', 'reinstatement_value', 'book_value',
        'is_manual_override', 'override_justification', 'reconciled_by_user_id',
    ];

    protected $casts = [
        'method_inputs' => 'array',
        'is_manual_override' => 'boolean',
    ];

    public function valuationAssignment(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Assignment\Models\ValuationAssignment::class, 'valuation_assignment_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
