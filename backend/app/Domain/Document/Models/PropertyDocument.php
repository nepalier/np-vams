<?php

declare(strict_types=1);

namespace App\Domain\Document\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PropertyDocument extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'documentable_type', 'documentable_id', 'category', 'document_type',
        'document_number', 'issue_date', 'expiry_date', 'issuing_authority', 'original_seen',
        'copy_received', 'online_verified', 'authority_verified', 'verification_status',
        'verification_remarks', 'uploaded_by', 'uploaded_at', 'storage_disk', 'file_path',
        'file_hash_sha256', 'current_version', 'confidentiality_level',
    ];

    protected $casts = [
        'issue_date' => 'date', 'expiry_date' => 'date', 'uploaded_at' => 'datetime',
        'original_seen' => 'boolean', 'copy_received' => 'boolean',
        'online_verified' => 'boolean', 'authority_verified' => 'boolean',
    ];

    public function documentable()
    {
        return $this->morphTo();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['verification_status', 'current_version'])->logOnlyDirty();
    }
}
