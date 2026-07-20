<?php

declare(strict_types=1);

namespace App\Domain\Risk\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessmentItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'risk_assessment_id', 'risk_indicator_id', 'weight_applied', 'remarks'];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(RiskIndicator::class, 'risk_indicator_id');
    }
}
