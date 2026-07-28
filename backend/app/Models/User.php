<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, BelongsToTenant, HasApiTokens, HasFactory, HasRoles, HasUuids, LogsActivity, Notifiable, SoftDeletes;

    protected $guard_name = 'web';

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'organization_branch_id',
        'client_id',
        'client_branch_id',
        'user_type',
        'name',
        'name_ne',
        'email',
        'mobile',
        'preferred_locale',
        'password',
        'mfa_secret',
        'mfa_enabled',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'mfa_secret',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'mfa_enabled' => 'boolean',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(OrganizationBranch::class, 'organization_branch_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Client\Models\Client::class);
    }

    public function clientBranch(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Client\Models\ClientBranch::class, 'client_branch_id');
    }

    /**
     * The one thing that actually distinguishes a client-portal login from
     * a tenant-staff login: client_id is set. Checked here rather than
     * relying on `user_type` alone, since `user_type` is a convenience
     * label -- this is the value IdentifyTenant middleware and every
     * ScopedToClientPortal model actually key off of.
     */
    public function isClientPortalUser(): bool
    {
        return $this->client_id !== null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active', 'organization_id', 'organization_branch_id'])
            ->logOnlyDirty();
    }
}
