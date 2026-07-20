<?php

declare(strict_types=1);

namespace App\Domain\Document\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use BelongsToTenant, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'property_document_id', 'version_number', 'storage_disk',
        'file_path', 'file_hash_sha256', 'uploaded_by', 'uploaded_at', 'change_remarks',
    ];

    protected $casts = ['uploaded_at' => 'datetime'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(PropertyDocument::class, 'property_document_id');
    }
}
