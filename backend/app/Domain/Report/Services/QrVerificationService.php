<?php

declare(strict_types=1);

namespace App\Domain\Report\Services;

use App\Domain\Report\Models\QrVerification;
use App\Domain\Report\Models\Report;
use Illuminate\Support\Str;

/**
 * Section 33. The public verification page must show ONLY: report number,
 * valuation firm, report date, property municipality/district, status,
 * revision number, signed-by name -- and must NEVER expose citizenship
 * number, loan amount, owner contact info, full financial details, or
 * sensitive documents. That allow-list is enforced by
 * `publicPayload()` below being the *only* method this service exposes
 * for reading verification data -- there is deliberately no method that
 * returns the full Report/ReportVersion/assignment graph to an
 * unauthenticated caller.
 */
class QrVerificationService
{
    public function issueToken(Report $report): QrVerification
    {
        return QrVerification::create([
            'tenant_id' => $report->tenant_id,
            'report_id' => $report->id,
            // Unguessable: not the report's own UUID (which could plausibly
            // be enumerated or leaked via another endpoint) -- a fresh
            // random token dedicated solely to public verification.
            'public_token' => Str::random(40),
            'status' => 'valid',
        ]);
    }

    public function revoke(QrVerification $verification, string $status): QrVerification
    {
        $verification->forceFill(['status' => $status])->save();

        return $verification;
    }

    /**
     * @return array<string, mixed>|null  null if the token is unknown (caller should 404, not distinguish
     *         "wrong token" from "revoked" in the HTTP response, to avoid a token-enumeration oracle)
     */
    public function publicPayload(string $token): ?array
    {
        $verification = QrVerification::withoutTenantScope()
            ->where('public_token', $token)
            ->with([
                'report.currentVersion.signature',
                'report.valuationAssignment.organization',
                'report.valuationAssignment.properties.property.district',
                'report.valuationAssignment.properties.property.localLevel',
            ])
            ->first();

        if ($verification === null) {
            return null;
        }

        $report = $verification->report;
        $assignment = $report->valuationAssignment;
        $firstProperty = $assignment?->properties?->first()?->property;

        return [
            'report_number' => $report->report_number,
            'valuation_firm' => $assignment?->organization?->name_en,
            'report_date' => $report->currentVersion?->generated_at?->toDateString(),
            'property_municipality' => $firstProperty?->localLevel?->name_en,
            'property_district' => $firstProperty?->district?->name_en,
            'status' => $verification->status,
            'revision_number' => $report->currentVersion?->version_number,
            'signed_by_name' => $report->currentVersion?->signature?->signer_name,
        ];
    }
}
