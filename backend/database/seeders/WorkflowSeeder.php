<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Workflow\Models\WorkflowStatus;
use App\Domain\Workflow\Models\WorkflowTransitionRule;
use Illuminate\Database\Seeder;

/**
 * Default 23-state assignment workflow (Step 1 Section 7). Edges are listed
 * as [from, to, allowed_roles|null, requires_remarks] so branches/loops
 * (correction -> resubmitted -> review) and terminal states (cancelled,
 * superseded) are explicit, not inferred from sequence order.
 */
class WorkflowSeeder extends Seeder
{
    private const STATUSES = [
        ['draft', 'Draft Request', 'मस्यौदा अनुरोध', 10, false],
        ['submitted', 'Submitted', 'पेश गरिएको', 20, false],
        ['assignment_accepted', 'Assignment Accepted', 'असाइनमेन्ट स्वीकृत', 30, false],
        ['documents_pending', 'Documents Pending', 'कागजात पेन्डिङ', 40, false],
        ['preliminary_verification', 'Preliminary Verification', 'प्रारम्भिक प्रमाणीकरण', 50, false],
        ['valuer_assigned', 'Valuer Assigned', 'मूल्याङ्कक तोकिएको', 60, false],
        ['site_visit_scheduled', 'Site Visit Scheduled', 'साइट भ्रमण तालिका', 70, false],
        ['field_inspection_in_progress', 'Field Inspection In Progress', 'फिल्ड निरीक्षण जारी', 80, false],
        ['inspection_completed', 'Inspection Completed', 'निरीक्षण सम्पन्न', 90, false],
        ['under_valuation', 'Under Valuation', 'मूल्याङ्कनमा', 100, false],
        ['draft_report_generated', 'Draft Report Generated', 'मस्यौदा प्रतिवेदन तयार', 110, false],
        ['under_technical_review', 'Under Technical Review', 'प्राविधिक समीक्षामा', 120, false],
        ['correction_requested', 'Correction Requested', 'सुधार अनुरोध', 130, false],
        ['resubmitted', 'Resubmitted', 'पुनः पेश गरिएको', 140, false],
        ['awaiting_approval', 'Awaiting Approval', 'स्वीकृतिको पर्खाइमा', 150, false],
        ['approved', 'Approved', 'स्वीकृत', 160, false],
        ['digitally_signed', 'Digitally Signed', 'डिजिटल हस्ताक्षरित', 170, false],
        ['report_issued', 'Report Issued', 'प्रतिवेदन जारी', 180, false],
        ['invoice_issued', 'Invoice Issued', 'बिजक जारी', 190, false],
        ['payment_received', 'Payment Received', 'भुक्तानी प्राप्त', 200, false],
        ['archived', 'Archived', 'संग्रहित', 210, true],
        ['revaluation_due', 'Revaluation Due', 'पुनर्मूल्याङ्कन आवश्यक', 220, false],
        ['cancelled', 'Cancelled', 'रद्द', 230, true],
        ['superseded', 'Superseded', 'प्रतिस्थापित', 240, true],
    ];

    /** [from, to, allowed_roles|null, requires_remarks] */
    private const EDGES = [
        ['draft', 'submitted', null, false],
        ['submitted', 'assignment_accepted', ['Valuation Firm Administrator', 'Branch Administrator'], false],
        ['submitted', 'cancelled', null, true],
        ['assignment_accepted', 'documents_pending', null, false],
        ['documents_pending', 'preliminary_verification', null, false],
        ['preliminary_verification', 'valuer_assigned', ['Valuation Firm Administrator', 'Branch Administrator'], false],
        ['valuer_assigned', 'site_visit_scheduled', ['Valuer or Engineer', 'Field Surveyor'], false],
        ['site_visit_scheduled', 'field_inspection_in_progress', ['Valuer or Engineer', 'Field Surveyor'], false],
        ['field_inspection_in_progress', 'inspection_completed', ['Valuer or Engineer', 'Field Surveyor'], false],
        ['inspection_completed', 'under_valuation', ['Valuer or Engineer'], false],
        ['under_valuation', 'draft_report_generated', ['Valuer or Engineer'], false],
        ['draft_report_generated', 'under_technical_review', ['Valuer or Engineer'], false],
        ['under_technical_review', 'correction_requested', ['Technical Reviewer'], true],
        ['under_technical_review', 'awaiting_approval', ['Technical Reviewer'], false],
        ['correction_requested', 'resubmitted', ['Valuer or Engineer'], false],
        ['resubmitted', 'under_technical_review', ['Technical Reviewer'], false],
        ['awaiting_approval', 'approved', ['Approving Authority'], false],
        ['awaiting_approval', 'correction_requested', ['Approving Authority'], true],
        ['awaiting_approval', 'cancelled', ['Approving Authority'], true],
        ['approved', 'digitally_signed', ['Approving Authority'], false],
        ['digitally_signed', 'report_issued', ['Approving Authority'], false],
        ['report_issued', 'invoice_issued', ['Finance Officer'], false],
        ['invoice_issued', 'payment_received', ['Finance Officer'], false],
        ['payment_received', 'archived', null, false],
        ['archived', 'revaluation_due', null, false],
        ['report_issued', 'superseded', ['Approving Authority'], true],
        ['report_issued', 'cancelled', ['Approving Authority'], true],
        // Cancellation permitted from any pre-issuance state:
        ['assignment_accepted', 'cancelled', null, true],
        ['documents_pending', 'cancelled', null, true],
        ['preliminary_verification', 'cancelled', null, true],
        ['valuer_assigned', 'cancelled', null, true],
    ];

    public function run(): void
    {
        $statusIds = [];

        foreach (self::STATUSES as [$code, $labelEn, $labelNe, $sequence, $isTerminal]) {
            $status = WorkflowStatus::updateOrCreate(
                ['code' => $code],
                ['label_en' => $labelEn, 'label_ne' => $labelNe, 'sequence' => $sequence, 'is_terminal' => $isTerminal]
            );
            $statusIds[$code] = $status->id;
        }

        foreach (self::EDGES as [$from, $to, $roles, $requiresRemarks]) {
            WorkflowTransitionRule::updateOrCreate(
                ['from_status_id' => $statusIds[$from], 'to_status_id' => $statusIds[$to]],
                ['allowed_roles' => $roles, 'requires_remarks' => $requiresRemarks]
            );
        }

        $this->command?->info('Seeded workflow: '.count(self::STATUSES).' statuses, '.count(self::EDGES).' transition rules.');
    }
}
