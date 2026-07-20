<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

/**
 * Reference implementation for the permission model described in Step 1,
 * Section 4: module + action + org (+ branch) (+ ownership).
 * Every future domain policy (Assignment, Property, Report, ...) follows
 * this same shape.
 */
class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('organizations.view');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->can('organizations.view')
            && $organization->tenant_id === $user->tenant_id;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->can('organizations.update')
            && $organization->tenant_id === $user->tenant_id
            && ($user->hasRole('Tenant Administrator') || $organization->id === $user->organization_id);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->can('organizations.delete')
            && $user->hasRole('Tenant Administrator')
            && $organization->tenant_id === $user->tenant_id;
    }
}
