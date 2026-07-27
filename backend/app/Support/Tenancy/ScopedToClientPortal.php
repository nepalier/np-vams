<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * Applied to any tenant-owned model that also carries a `client_id` column
 * (ValuationAssignment, Invoice, Report) -- narrows visibility to the
 * authenticated client-portal user's own client, the same "defense in
 * depth" way BelongsToTenant narrows visibility to one tenant. A model
 * using this trait is automatically portal-safe; no controller has to
 * remember to filter by client_id itself.
 */
trait ScopedToClientPortal
{
    public static function bootScopedToClientPortal(): void
    {
        static::addGlobalScope(new ClientPortalScope);
    }

    /**
     * Explicit, auditable escape hatch for staff-side code paths that
     * legitimately need to see every client's rows within the tenant
     * (e.g. the firm-wide dashboard). Never call this based on
     * unauthenticated or client-portal-originated input.
     */
    public static function withoutClientPortalScope()
    {
        return static::withoutGlobalScope(ClientPortalScope::class);
    }
}
