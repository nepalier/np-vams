<?php

declare(strict_types=1);

namespace App\Domain\Client\Http\Controllers;

use App\Domain\Client\Http\Requests\StoreClientRequest;
use App\Domain\Client\Http\Requests\UpdateClientRequest;
use App\Domain\Client\Http\Resources\ClientResource;
use App\Domain\Client\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController
{
    public function index(Request $request): JsonResponse
    {
        $request->user()->can('assignments.view') || abort(403);

        $clients = Client::query()
            ->when($request->filled('client_type'), fn ($q) => $q->where('client_type', $request->string('client_type')))
            ->when($request->filled('search'), fn ($q) => $q->where('name_en', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name_en')
            ->paginate($request->integer('per_page', 20));

        return ClientResource::collection($clients)->response();
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create([
            'tenant_id' => $request->user()->tenant_id,
            ...$request->validated(),
        ]);

        return (new ClientResource($client))->response()->setStatusCode(201);
    }

    public function show(Client $client): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        return (new ClientResource($client->load('branches')))->response();
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $client->update($request->validated());

        return (new ClientResource($client->fresh()))->response();
    }
}
