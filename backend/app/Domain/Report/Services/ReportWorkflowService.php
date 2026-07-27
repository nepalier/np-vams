<?php

declare(strict_types=1);

namespace App\Domain\Report\Services;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Report\Models\DigitalSignature;
use App\Domain\Report\Models\Report;
use App\Domain\Valuation\Models\ValuationReconciliation;
use App\Domain\Workflow\Services\WorkflowEngine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orchestrates the generate -> approve (handled by ApprovalService,
 * upstream of this class) -> sign -> issue lifecycle from Sections 32-34,
 * tying report/version/signature/QR state to the SAME workflow status the
 * assignment is in, so the two can never say different things (e.g. an
 * assignment showing "report_issued" while no report_versions row with a
 * signature actually exists).
 */
class ReportWorkflowService
{
    public function __construct(
        private readonly ReportGenerationService $generationService,
        private readonly ReportIntegrityService $integrityService,
        private readonly QrVerificationService $qrService,
        private readonly WorkflowEngine $workflowEngine,
    ) {}

    public function generateDraft(
        ValuationAssignment $assignment,
        ValuationReconciliation $reconciliation,
        array $methodResults,
        User $user,
    ): Report {
        $report = Report::firstOrCreate(
            ['tenant_id' => $assignment->tenant_id, 'valuation_assignment_id' => $assignment->id],
            ['status' => 'drafting', 'client_id' => $assignment->client_id]
        );

        return DB::transaction(function () use ($report, $assignment, $reconciliation, $methodResults, $user) {
            $nextVersionNumber = ($report->versions()->max('version_number') ?? 0) + 1;

            $pdfBytes = $this->generationService->renderPdf($report, $assignment, $reconciliation, $methodResults, $nextVersionNumber);
            $this->integrityService->createVersion($report, $pdfBytes, 'pdf', $user->id);

            $docxBytes = $this->generationService->renderDocx($report, $assignment, $reconciliation, $methodResults, $nextVersionNumber);
            $this->integrityService->createVersion($report, $docxBytes, 'docx', $user->id);

            $report->forceFill(['status' => 'draft_generated'])->save();

            return $report->fresh();
        });
    }

    /** Approving Authority only -- enforced by the caller's policy check + the workflow edge's allowed_roles. */
    public function sign(Report $report, ValuationAssignment $assignment, User $user, array $certificate): Report
    {
        if ($assignment->status !== 'approved') {
            throw new RuntimeException('The assignment must be in the "approved" workflow status before a report can be signed.');
        }

        $pdfVersion = $report->versions()->where('format', 'pdf')->latest('version_number')->first();

        if ($pdfVersion === null) {
            throw new RuntimeException('No PDF version exists to sign. Generate the report draft first.');
        }

        return DB::transaction(function () use ($report, $assignment, $user, $certificate, $pdfVersion) {
            DigitalSignature::create([
                'tenant_id' => $report->tenant_id,
                'report_version_id' => $pdfVersion->id,
                'signed_by_user_id' => $user->id,
                'signer_name' => $certificate['signer_name'] ?? $user->name,
                'signer_license_number' => $certificate['signer_license_number'] ?? null,
                'certificate_serial' => $certificate['certificate_serial'] ?? null,
                'certificate_issuer' => $certificate['certificate_issuer'] ?? null,
                'certificate_valid_from' => $certificate['certificate_valid_from'] ?? null,
                'certificate_valid_until' => $certificate['certificate_valid_until'] ?? null,
                'organization_seal_path' => $certificate['organization_seal_path'] ?? null,
                'signed_file_hash_sha256' => $pdfVersion->file_hash_sha256,
                'signed_at' => now(),
            ]);

            $this->integrityService->lock($report);
            $report->forceFill(['status' => 'signed'])->save();

            $this->workflowEngine->transition($assignment, 'digitally_signed', $user);

            return $report->fresh();
        });
    }

    public function issue(Report $report, ValuationAssignment $assignment, User $user): Report
    {
        if ($assignment->status !== 'digitally_signed') {
            throw new RuntimeException('The assignment must be in the "digitally_signed" workflow status before a report can be issued.');
        }

        return DB::transaction(function () use ($report, $assignment, $user) {
            if (empty($report->report_number)) {
                // 1:1 with the assignment (unique constraint on
                // valuation_assignment_id), so deriving the report number
                // from the already-unique assignment number is safe and
                // needs no separate sequence generator.
                $reportNumber = str_replace('VAL-', 'RPT-', $assignment->assignment_number);
                $report->forceFill(['report_number' => $reportNumber])->save();
            }

            $report->forceFill(['status' => 'issued'])->save();

            $this->qrService->issueToken($report);

            $this->workflowEngine->transition($assignment, 'report_issued', $user);

            return $report->fresh();
        });
    }

    public function cancel(Report $report, ValuationAssignment $assignment, User $user, string $reason): Report
    {
        if ($report->status !== 'issued') {
            throw new RuntimeException('Only an issued report can be cancelled.');
        }

        return DB::transaction(function () use ($report, $assignment, $user, $reason) {
            $report->forceFill(['status' => 'cancelled'])->save();
            $report->qrVerification()->update(['status' => 'cancelled']);

            $this->workflowEngine->transition($assignment, 'cancelled', $user, $reason);

            return $report->fresh();
        });
    }

    /**
     * A superseded report is replaced by a NEW assignment/report pair
     * (e.g. a corrected revaluation) -- this method only marks the OLD
     * report superseded and revokes its QR token; it does not create the
     * replacement, which is a normal new assignment with
     * is_revaluation=true and parent_assignment_id pointing back here
     * (Section 6).
     */
    public function supersede(Report $report, ValuationAssignment $assignment, User $user, string $reason): Report
    {
        if ($report->status !== 'issued') {
            throw new RuntimeException('Only an issued report can be superseded.');
        }

        return DB::transaction(function () use ($report, $assignment, $user, $reason) {
            $report->forceFill(['status' => 'superseded'])->save();
            $report->qrVerification()->update(['status' => 'superseded']);

            $this->workflowEngine->transition($assignment, 'superseded', $user, $reason);

            return $report->fresh();
        });
    }
}
