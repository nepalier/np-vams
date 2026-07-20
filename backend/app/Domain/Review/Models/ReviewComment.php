<?php

declare(strict_types=1);

namespace App\Domain\Review\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReviewComment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'valuation_assignment_id', 'section', 'comment_type', 'comment', 'severity',
        'is_resolved', 'resolved_by_user_id', 'resolved_at', 'created_by_user_id',
    ];

    protected $casts = ['is_resolved' => 'boolean', 'resolved_at' => 'datetime'];
}
