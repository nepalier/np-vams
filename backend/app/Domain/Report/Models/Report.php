<?php

declare(strict_types=1);

namespace App\Domain\Report\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Report extends Model
{
    use BelongsToTenant, HasUuids, LogsActivity;

    protected $fillable = [
        'tenant_id', 'valuation_assignment_id', 'report_template_id',
        'report_number', 'status', 'current_version_id', 'is_locked',
    ];

    protected $casts = ['is_locked' => 'boolean'];

    public function versions(): HasMany
    {
        return $this->hasMany(ReportVersion::class);
    }

    public function valuationAssignment(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Assignment\Models\ValuationAssignment::class, 'valuation_assignment_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ReportVersion::class, 'current_version_id');
    }

    public function qrVerification(): HasMany
    {
        return $this->hasMany(QrVerification::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
