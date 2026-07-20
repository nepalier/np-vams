<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransitionRule extends Model
{
    protected $fillable = ['from_status_id', 'to_status_id', 'allowed_roles', 'requires_remarks'];

    protected $casts = ['allowed_roles' => 'array', 'requires_remarks' => 'boolean'];

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'to_status_id');
    }
}
