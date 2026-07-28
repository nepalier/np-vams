<?php

declare(strict_types=1);

namespace App\Domain\Building\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingConditionAssessmentItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'building_condition_assessment_id', 'item_type', 'condition_rating', 'remarks'];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(BuildingConditionAssessment::class, 'building_condition_assessment_id');
    }

    public function photos()
    {
        return $this->morphMany(\App\Domain\Document\Models\PropertyDocument::class, 'documentable');
    }
}
