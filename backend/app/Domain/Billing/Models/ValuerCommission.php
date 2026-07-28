<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ValuerCommission extends Model
{
    use BelongsToTenant, HasUuids, LogsActivity;

    protected $fillable = [
        'tenant_id', 'valuation_assignment_id', 'user_id', 'commission_type', 'commission_rate_pct',
        'base_amount', 'commission_amount', 'status', 'approved_by_user_id', 'approved_at',
        'paid_at', 'payment_reference', 'remarks',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Assignment\Models\ValuationAssignment::class, 'valuation_assignment_id');
    }

    public function valuer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
