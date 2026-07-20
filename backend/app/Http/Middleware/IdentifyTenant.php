<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant from the AUTHENTICATED USER, never from a
 * client-supplied header/param/body value. This is deliberate: allowing the
 * client to name its own tenant_id would let any authenticated user request
 * another tenant's data by simply changing a header.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null) {
            app()->instance('currentTenantId', $user->tenant_id);
            app()->instance('currentOrganizationId', $user->organization_id);
            app()->instance('currentBranchId', $user->organization_branch_id);
        }

        return $next($request);
    }
}
