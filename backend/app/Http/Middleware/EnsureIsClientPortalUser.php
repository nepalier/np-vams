<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the /api/v1/portal/* route group: blocks tenant staff from
 * accessing portal-shaped endpoints (which assume request.user()->client_id
 * is set) and, symmetrically, blocks a client-portal user from ever being
 * routed into staff-only territory by mistake.
 */
class EnsureIsClientPortalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isClientPortalUser()) {
            abort(403, 'This endpoint is only available to client-portal accounts.');
        }

        return $next($request);
    }
}
