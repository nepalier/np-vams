<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Insert-only audit record (Step 1 Section 34: "immutable audit records").
 * No UPDATED_AT, no soft deletes -- once written, a transition row is
 * permanent history, matching the immutability requirement.
 */
class WorkflowTransition extends Model
{
    use BelongsToTenant, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'organization_id', 'organization_branch_id',
        'transitionable_type', 'transitionable_id',
        'previous_status', 'new_status', 'user_id',
        'remarks', 'attachments', 'ip_address', 'device_info',
    ];

    protected $casts = ['attachments' => 'array'];

    public function transitionable()
    {
        return $this->morphTo();
    }
}
