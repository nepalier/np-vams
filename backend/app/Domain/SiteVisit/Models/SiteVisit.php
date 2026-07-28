<?php

declare(strict_types=1);

namespace App\Domain\SiteVisit\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteVisit extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'valuation_assignment_id', 'property_id', 'scheduled_at', 'checked_in_at',
        'check_in_latitude', 'check_in_longitude', 'owner_representative_confirmed',
        'owner_representative_name', 'field_checklist', 'field_notes', 'inspection_completed',
        'inspection_completed_at', 'inspection_signature_path', 'witness_information', 'status',
        'sync_status', 'last_synced_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime', 'checked_in_at' => 'datetime',
        'owner_representative_confirmed' => 'boolean', 'field_checklist' => 'array',
        'inspection_completed' => 'boolean', 'inspection_completed_at' => 'datetime',
        'witness_information' => 'array', 'last_synced_at' => 'datetime',
    ];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'site_visit_members')->withPivot('role_on_visit');
    }

    public function photos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SitePhoto::class);
    }

    /**
     * Enforces Step 1 Section 17: "Prevent inspection completion if
     * mandatory information is missing." Called before allowing the
     * workflow transition into inspection_completed.
     */
    public function canBeMarkedComplete(): bool
    {
        return $this->checked_in_at !== null
            && $this->owner_representative_confirmed === true
            && ! empty($this->field_checklist)
            && $this->check_in_latitude !== null
            && $this->check_in_longitude !== null;
    }
}
