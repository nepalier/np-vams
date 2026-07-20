<?php

declare(strict_types=1);

namespace App\Domain\Report\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Insert-only artifact record (Section 34). No UPDATED_AT, no soft delete:
 * once a version exists it is never mutated, only superseded by a new row.
 */
class ReportVersion extends Model
{
    use BelongsToTenant, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'report_id', 'version_number', 'format', 'storage_disk', 'file_path',
        'file_hash_sha256', 'generated_by_user_id', 'generated_at', 'superseded_by_id', 'supersede_reason',
    ];

    protected $casts = ['generated_at' => 'datetime'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function signature(): HasOne
    {
        return $this->hasOne(DigitalSignature::class);
    }
}
