<?php

declare(strict_types=1);

namespace App\Domain\Report\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DigitalSignature extends Model
{
    use BelongsToTenant, HasUuids, LogsActivity;

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'report_version_id', 'signed_by_user_id', 'signer_name', 'signer_license_number',
        'certificate_serial', 'certificate_issuer', 'certificate_valid_from', 'certificate_valid_until',
        'organization_seal_path', 'signed_file_hash_sha256', 'signed_at',
    ];

    protected $casts = [
        'certificate_valid_from' => 'datetime',
        'certificate_valid_until' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(ReportVersion::class, 'report_version_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
