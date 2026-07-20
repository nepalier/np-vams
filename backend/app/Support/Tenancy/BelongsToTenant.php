<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Model;

/**
 * Applied to every tenant-owned model.
 *
 * This is the "defense in depth" tenancy layer described in the Step 1
 * architecture: even if a controller or policy has a bug, a tenant-scoped
 * model can never return another tenant's rows, because the scope is
 * attached at the model boot level rather than opted into per-query.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if (empty($model->tenant_id) && app()->bound('currentTenantId')) {
                $model->tenant_id = app('currentTenantId');
            }
        });
    }

    /**
     * Explicit, auditable escape hatch for platform/support roles only.
     * Never call this based on user input — only from code paths already
     * gated behind a Super Administrator / Platform Administrator policy.
     */
    public static function withoutTenantScope()
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}
