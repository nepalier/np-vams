<?php

declare(strict_types=1);

namespace App\Domain\Comparable\Http\Controllers;

use App\Domain\Comparable\Http\Requests\StoreComparablePropertyRequest;
use App\Domain\Comparable\Models\ComparableProperty;
use App\Support\Geo\GeoMathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComparablePropertyController
{
    public function index(Request $request): JsonResponse
    {
        $request->user()->can('assignments.view') || abort(403);

        $comparables = ComparableProperty::query()
            ->when($request->filled('district_id'), fn ($q) => $q->where('district_id', $request->integer('district_id')))
            ->when($request->filled('property_type_id'), fn ($q) => $q->where('property_type_id', $request->integer('property_type_id')))
            ->when($request->filled('reliability_grade'), fn ($q) => $q->where('reliability_grade', $request->string('reliability_grade')))
            ->orderByDesc('transaction_date')
            ->paginate($request->integer('per_page', 20));

        return response()->json($comparables);
    }

    public function store(StoreComparablePropertyRequest $request): JsonResponse
    {
        $comparable = ComparableProperty::create([
            'tenant_id' => $request->user()->tenant_id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => $comparable], 201);
    }

    public function show(ComparableProperty $comparable): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        return response()->json(['data' => $comparable]);
    }

    /**
     * Section 21/22's actual intended workflow: find recorded comparables
     * physically near the subject property, rather than a valuer typing
     * ad-hoc numbers into the Market Comparison calculation from memory.
     * Uses the real GeoMathService haversine distance (Section 20 batch),
     * not a bounding-box approximation.
     */
    public function nearby(Request $request, GeoMathService $geoMath): JsonResponse
    {
        $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:50'],
        ]);

        $lat = (float) $request->input('latitude');
        $lng = (float) $request->input('longitude');
        $radiusKm = (float) $request->input('radius_km', 2.0);

        $candidates = ComparableProperty::whereNotNull('latitude')->whereNotNull('longitude')->get();

        $withinRadius = $candidates
            ->map(function (ComparableProperty $c) use ($geoMath, $lat, $lng) {
                $distanceM = $geoMath->distanceBetweenPoints($lat, $lng, (float) $c->latitude, (float) $c->longitude);

                return ['comparable' => $c, 'distance_m' => $distanceM];
            })
            ->filter(fn ($row) => $row['distance_m'] !== null && $row['distance_m'] <= $radiusKm * 1000)
            ->sortBy('distance_m')
            ->values()
            ->map(fn ($row) => [...$row['comparable']->toArray(), 'distance_m' => round($row['distance_m'], 1)]);

        return response()->json(['data' => $withinRadius]);
    }
}
