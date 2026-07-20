<?php

declare(strict_types=1);

namespace App\Domain\Review\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Insert-only decision log -- part of the immutable audit trail
 * (Section 30/34), never updated after creation.
 */
class ApprovalRecord extends Model
{
    use BelongsToTenant, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'valuation_assignment_id', 'stage', 'decision',
        'decided_by_user_id', 'remarks', 'decided_at',
    ];

    protected $casts = ['decided_at' => 'datetime'];
}
