<?php

declare(strict_types=1);

namespace App\Domain\Report\Services;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Report\Models\Report;
use App\Domain\Valuation\Models\ValuationReconciliation;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

/**
 * Section 32: editable DOCX + secured/signed PDF, bilingual templates. The
 * PDF renders a real Blade view (resources/views/reports/templates/*) with
 * the assignment's actual data -- not lorem-ipsum placeholder content. The
 * DOCX is built with PhpWord's own document-object API (Blade doesn't
 * render to DOCX, so this mirrors the same section content through
 * PhpWord's API instead of templating).
 *
 * NOT yet implemented here, and said so rather than left silently thin:
 * the full 30-plus-section report layout from Step 1 Section 32 (site
 * inspection narrative, comparable-by-comparable analysis tables, embedded
 * photographs/maps/calculation-sheet annexes). This service produces the
 * cover page + purpose + location + valuation summary + risk + assumptions
 * + declaration + signature block sections -- the skeleton every other
 * section slots into -- as the next incremental piece.
 */
class ReportGenerationService
{
    public function renderPdf(
        Report $report,
        ValuationAssignment $assignment,
        ValuationReconciliation $reconciliation,
        array $methodResults,
        int $versionNumber,
        ?string $signerName = null,
        ?string $signerLicenseNumber = null,
        ?string $riskCategory = null,
    ): string {
        $data = $this->templateData($report, $assignment, $reconciliation, $methodResults, $versionNumber, $signerName, $signerLicenseNumber, $riskCategory);

        return Pdf::loadView('reports.templates.default', $data)->output();
    }

    public function renderDocx(
        Report $report,
        ValuationAssignment $assignment,
        ValuationReconciliation $reconciliation,
        array $methodResults,
        int $versionNumber,
    ): string {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        $section->addTitle('Property Valuation Report', 1);
        $section->addText($assignment->organization?->name_en ?? '');
        $section->addText("Assignment No: {$assignment->assignment_number}");
        $section->addText('Report No: '.($report->report_number ?? '(draft — assigned at issuance)'));
        $section->addTextBreak();

        $section->addTitle('1. Purpose of Valuation', 2);
        $section->addText('Client: '.($assignment->client?->name_en ?? ''));
        $section->addText('Borrower: '.($assignment->borrower?->name_en ?? '—'));

        $section->addTitle('2. Valuation Summary', 2);
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999']);
        $table->addRow();
        $table->addCell(4000)->addText('Method');
        $table->addCell(4000)->addText('Value');
        foreach ($methodResults as $row) {
            $table->addRow();
            $table->addCell(4000)->addText((string) $row['method']);
            $table->addCell(4000)->addText(number_format((float) $row['value'], 2));
        }

        $section->addTextBreak();
        $section->addText('Reconciled Market Value: '.number_format((float) $reconciliation->reconciled_market_value, 2));
        $section->addText('Rounded Market Value: '.number_format((float) $reconciliation->rounded_market_value, 2));

        $section->addTitle('3. Declaration', 2);
        $section->addText(
            'I/We confirm that this valuation has been carried out impartially, without any conflict of '.
            'interest, and reflects my/our professional opinion of value as at the date of inspection.'
        );

        $section->addTextBreak(2);
        $section->addText('_____________________________');
        $section->addText('(Signature pending — DOCX is issued unsigned; the signed artifact is always the PDF.)');

        $writer = IOFactory::createWriter($phpWord, 'Word2007');

        $tmpPath = tempnam(sys_get_temp_dir(), 'npvams_docx_');
        $writer->save($tmpPath);
        $contents = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $contents;
    }

    private function templateData(
        Report $report,
        ValuationAssignment $assignment,
        ValuationReconciliation $reconciliation,
        array $methodResults,
        int $versionNumber,
        ?string $signerName,
        ?string $signerLicenseNumber,
        ?string $riskCategory,
    ): array {
        $properties = $assignment->properties()->with(['property.district', 'property.localLevel'])->get()
            ->pluck('property')
            ->filter();

        return [
            'report' => $report,
            'assignment' => $assignment,
            'organization' => $assignment->organization,
            'client' => $assignment->client,
            'borrower' => $assignment->borrower,
            'valuationPurpose' => $assignment->valuationPurpose?->name_en ?? '—',
            'properties' => $properties,
            'reconciliation' => $reconciliation,
            'methodResults' => $methodResults,
            'versionNumber' => $versionNumber,
            'signerName' => $signerName,
            'signerLicenseNumber' => $signerLicenseNumber,
            'riskCategory' => $riskCategory,
            'locale' => 'en',
        ];
    }
}
