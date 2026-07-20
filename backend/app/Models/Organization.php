<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Organization extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name_en',
        'name_ne',
        'organization_type',
        'registration_number',
        'registration_authority',
        'registration_date',
        'pan_number',
        'vat_number',
        'province_id',
        'district_id',
        'local_level_id',
        'ward_id',
        'postal_address',
        'telephone',
        'mobile',
        'email',
        'website',
        'authorized_contact_person',
        'logo_path',
        'letterhead_path',
        'digital_seal_path',
        'subscription_plan',
        'subscription_starts_at',
        'subscription_ends_at',
        'account_status',
        'approval_status',
        'is_suspended',
        'is_blacklisted',
        'remarks',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'subscription_starts_at' => 'date',
        'subscription_ends_at' => 'date',
        'is_suspended' => 'boolean',
        'is_blacklisted' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(OrganizationBranch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->logAll();
    }
}
