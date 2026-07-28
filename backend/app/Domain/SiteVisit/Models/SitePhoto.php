<?php

declare(strict_types=1);

namespace App\Domain\SiteVisit\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePhoto extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'site_visit_id', 'property_id', 'category', 'storage_disk', 'original_path',
        'watermarked_path', 'file_hash_sha256', 'latitude', 'longitude', 'captured_at',
        'uploaded_by_user_id', 'remarks',
    ];

    protected $casts = ['captured_at' => 'datetime'];

    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(SiteVisit::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Property\Models\Property::class);
    }
}
