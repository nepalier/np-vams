<?php

declare(strict_types=1);

namespace App\Domain\ClientPortal\Http\Controllers;

use App\Domain\Client\Models\Client;
use App\Domain\ClientPortal\Http\Requests\InviteClientPortalUserRequest;
use App\Domain\ClientPortal\Services\ClientPortalUserService;
use Illuminate\Http\JsonResponse;

/**
 * STAFF-SIDE controller (tenant staff manage which of their client's
 * people get a portal login) -- not itself behind EnsureIsClientPortalUser,
 * the opposite: ordinary tenant auth + the client_portal_users.invite
 * permission.
 */
class ClientPortalUserController
{
    public function __construct(private readonly ClientPortalUserService $service) {}

    public function store(InviteClientPortalUserRequest $request, Client $client): JsonResponse
    {
        $result = $this->service->invite($client, $request->validated(), $request->input('role'));

        return response()->json([
            'data' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                // Returned once, here, at creation time only -- never
                // stored anywhere retrievable afterward. The inviting
                // staff member is responsible for relaying it securely.
                'temporary_password' => $result['temporary_password'],
            ],
        ], 201);
    }

    public function index(Client $client): JsonResponse
    {
        request()->user()->can('client_portal_users.invite') || abort(403);

        $users = \App\Models\User::where('client_id', $client->id)
            ->get(['id', 'name', 'email', 'client_branch_id', 'is_active', 'created_at']);

        return response()->json(['data' => $users]);
    }
}
