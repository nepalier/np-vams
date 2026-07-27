<?php

declare(strict_types=1);

namespace App\Domain\ClientPortal\Services;

use App\Domain\Client\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The only place a client-portal User is ever created. Deliberately
 * refuses to create one with organization_id set (that would make a
 * single account simultaneously a tenant-staff login AND a client login,
 * defeating the entire point of ClientPortalScope) -- a portal account is
 * client_id-only, always.
 */
class ClientPortalUserService
{
    public function invite(Client $client, array $data, ?string $roleName = null): array
    {
        if ($client->tenant_id === null) {
            throw new RuntimeException('Client has no tenant context.');
        }

        $temporaryPassword = Str::password(16);

        $user = User::create([
            'tenant_id' => $client->tenant_id,
            'organization_id' => null,
            'organization_branch_id' => null,
            'client_id' => $client->id,
            'client_branch_id' => $data['client_branch_id'] ?? null,
            'user_type' => 'client_portal',
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'password' => Hash::make($temporaryPassword),
            'is_active' => true,
        ]);

        $user->assignRole($roleName ?? 'Client Institution Administrator');

        // Email delivery of the temporary password is a follow-on (needs
        // the notification-template layer from Phase 7b wired to a new
        // 'client_portal_invited' event) -- returned directly here for now
        // so the inviting staff member can relay it out-of-band.
        return ['user' => $user, 'temporary_password' => $temporaryPassword];
    }

    public function deactivate(User $portalUser): User
    {
        if (! $portalUser->isClientPortalUser()) {
            throw new RuntimeException('This user is not a client-portal account.');
        }

        $portalUser->forceFill(['is_active' => false])->save();

        return $portalUser;
    }
}
