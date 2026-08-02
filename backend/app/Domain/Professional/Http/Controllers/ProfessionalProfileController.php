<?php

declare(strict_types=1);

namespace App\Domain\Professional\Http\Controllers;

use App\Domain\Professional\Http\Requests\UpdateProfessionalProfileRequest;
use App\Domain\Professional\Models\ProfessionalProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfessionalProfileController
{
    /** The caller's own profile -- self-service, matches the request's authorize() design. */
    public function show(Request $request): JsonResponse
    {
        $profile = ProfessionalProfile::where('user_id', $request->user()->id)->first();

        return response()->json(['data' => $profile]);
    }

    public function update(UpdateProfessionalProfileRequest $request): JsonResponse
    {
        $profile = ProfessionalProfile::updateOrCreate(
            ['tenant_id' => $request->user()->tenant_id, 'user_id' => $request->user()->id],
            $request->validated(),
        );

        return response()->json(['data' => $profile]);
    }

    /**
     * Admin-facing compliance overview: every professional profile in
     * the tenant, ordered so the soonest-expiring ones surface first --
     * an admin checking "who needs to renew soon" is the actual use
     * case, not an alphabetical user list.
     */
    public function index(Request $request): JsonResponse
    {
        $request->user()->hasAnyRole(['Tenant Administrator', 'Valuation Firm Administrator']) || abort(403);

        $profiles = ProfessionalProfile::with('user')
            ->orderByRaw('COALESCE(license_expiry_date, registration_validity_date) IS NULL')
            ->orderByRaw('COALESCE(license_expiry_date, registration_validity_date) ASC')
            ->get();

        return response()->json(['data' => $profiles]);
    }
}
