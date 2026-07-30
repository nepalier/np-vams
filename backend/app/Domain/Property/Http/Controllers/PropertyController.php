<?php

declare(strict_types=1);

namespace App\Domain\Property\Http\Controllers;

use App\Domain\Property\Http\Requests\StorePropertyRequest;
use App\Domain\Property\Http\Requests\UpdatePropertyRequest;
use App\Domain\Property\Http\Resources\PropertyResource;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Services\PropertyCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController
{
    public function __construct(private readonly PropertyCodeGenerator $codeGenerator) {}

    public function index(Request $request): JsonResponse
    {
        $request->user()->can('assignments.view') || abort(403);

        $properties = Property::query()
            ->with(['district', 'localLevel'])
            ->when($request->filled('district_id'), fn ($q) => $q->where('district_id', $request->integer('district_id')))
            ->when($request->filled('search'), fn ($q) => $q->where('property_name', 'like', '%'.$request->string('search').'%'))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return PropertyResource::collection($properties)->response();
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $property = Property::create([
            'tenant_id' => $request->user()->tenant_id,
            'property_code' => $this->codeGenerator->next($request->user()->tenant_id),
            ...$request->validated(),
        ]);

        return (new PropertyResource($property))->response()->setStatusCode(201);
    }

    public function show(Property $property): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        return (new PropertyResource($property->load(['district', 'localLevel', 'parcels', 'buildings'])))->response();
    }

    public function update(UpdatePropertyRequest $request, Property $property): JsonResponse
    {
        $property->update($request->validated());

        return (new PropertyResource($property->fresh()))->response();
    }
}
