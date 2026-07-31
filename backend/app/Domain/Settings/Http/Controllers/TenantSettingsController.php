<?php

declare(strict_types=1);

namespace App\Domain\Settings\Http\Controllers;

use App\Domain\Settings\Http\Requests\UpdateTenantSettingsRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * The org-wide "Settings" area for the nine valuation-percentage defaults
 * introduced across the last few batches -- sits in the resolution chain
 * between a client's own bank-specific override (Clients screen) and
 * each engine's final hard-coded fallback. Single-tenant scope: reads
 * and writes only the CALLER's own tenant row, never accepts a tenant_id
 * from the request body.
 */
class TenantSettingsController
{
    private const FIELDS = [
        'default_land_rate_government_weight_pct', 'default_land_rate_market_weight_pct', 'default_distress_value_pct',
        'default_vehicle_scrap_pct', 'default_vehicle_depreciation_pct_per_annum', 'default_vehicle_other_cost_pct_per_annum',
        'default_building_sanitary_fixture_pct', 'default_building_electrical_fixture_pct', 'default_building_depreciation_pct_per_annum',
    ];

    public function show(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($request->user()->tenant_id);

        return response()->json(['data' => Arr::only($tenant->toArray(), self::FIELDS)]);
    }

    public function update(UpdateTenantSettingsRequest $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($request->user()->tenant_id);
        $tenant->update($request->only(self::FIELDS)); // Request::only() IS real -- FormRequest inherits it from Illuminate\Http\Request

        return response()->json(['data' => Arr::only($tenant->fresh()->toArray(), self::FIELDS)]);
    }
}
