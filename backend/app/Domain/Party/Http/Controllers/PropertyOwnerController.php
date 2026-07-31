<?php

declare(strict_types=1);

namespace App\Domain\Party\Http\Controllers;

use App\Domain\Party\Http\Requests\StorePropertyOwnerRequest;
use App\Domain\Party\Models\PropertyOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyOwnerController
{
    public function index(Request $request): JsonResponse
    {
        $request->user()->can('assignments.view') || abort(403);

        $owners = PropertyOwner::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name_en', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name_en')
            ->paginate($request->integer('per_page', 50));

        return response()->json($owners);
    }

    public function store(StorePropertyOwnerRequest $request): JsonResponse
    {
        $owner = PropertyOwner::create([
            'tenant_id' => $request->user()->tenant_id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => $owner], 201);
    }

    public function show(PropertyOwner $owner): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        return response()->json(['data' => $owner]);
    }
}
