<?php

declare(strict_types=1);

namespace App\Domain\Report\Http\Controllers;

use App\Domain\Report\Services\QrVerificationService;
use Illuminate\Http\JsonResponse;

/**
 * PUBLIC, UNAUTHENTICATED endpoint (Section 33) -- deliberately registered
 * outside the auth:sanctum/tenant middleware group in routes/api.php.
 * Returns only QrVerificationService::publicPayload()'s allow-listed
 * fields; never touches the Report/ReportVersion/ValuationAssignment
 * models directly, so there is no path by which a future field added to
 * those models leaks here by accident.
 */
class QrVerificationController
{
    public function __construct(private readonly QrVerificationService $service) {}

    public function show(string $token): JsonResponse
    {
        $payload = $this->service->publicPayload($token);

        if ($payload === null) {
            // Same 404 whether the token is unknown or was never issued --
            // no distinct "invalid vs revoked" response, to avoid handing
            // an enumeration oracle to a scripted prober.
            return response()->json([
                'errors' => [['status' => '404', 'title' => 'NotFound', 'detail' => 'No report found for this verification code.']],
            ], 404);
        }

        return response()->json(['data' => $payload]);
    }
}
