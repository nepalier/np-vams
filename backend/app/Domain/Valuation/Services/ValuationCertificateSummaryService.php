<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Services;

use App\Support\Text\AmountToWordsConverter;

/**
 * Generates the "Valuation Certificate" summary block found -- almost
 * word-for-word identical -- across every real bank reference report
 * format this system was built from: a numbered declaration list, the
 * Weighted Fair Market Value with its actual government/market split
 * named explicitly (never a hard-coded "70% Gov. + 30% Market" string,
 * since that split varies bank to bank), the amount in words, the
 * Distress Value and its own amount in words, and a closing declaration
 * paragraph.
 */
class ValuationCertificateSummaryService
{
    public function __construct(private readonly AmountToWordsConverter $amountToWords) {}

    /**
     * @param  array{
     *   weighted_fair_market_value: float,
     *   government_weight_pct: float,
     *   market_weight_pct: float,
     *   distress_value_pct: float,
     *   inspection_date: string,
     *   comments: string|null,
     * }  $input
     */
    public function generate(array $input): array
    {
        $fmv = round($input['weighted_fair_market_value'], 2);
        $distressPct = $input['distress_value_pct'];
        $distressValue = round($fmv * $distressPct / 100, 2);

        $govtPct = $this->formatPct($input['government_weight_pct']);
        $marketPct = $this->formatPct($input['market_weight_pct']);

        return [
            'weighted_fair_market_value' => $fmv,
            'weighted_fair_market_value_label' => "Weighted Fair Market Value of the property ({$govtPct}% Gov. + {$marketPct}% Market)",
            'weighted_fair_market_value_in_words' => $this->amountToWords->convert($fmv),
            'distress_value' => $distressValue,
            'distress_value_label' => "Distress Value of the property ({$this->formatPct($distressPct)}% of FMV)",
            'distress_value_in_words' => $this->amountToWords->convert($distressValue),
            // Standard declaration wording, identical across every
            // reference document -- the inspection date is the only
            // per-report variable within it.
            'declarations' => [
                'We have physically inspected, verified and measured the property.',
                'We have no direct and indirect interest in the said property.',
                'The information furnished in the report are true and correct to the best of knowledge and belief which are based on the document and information collected from the client and resident during our visit.',
                'The market condition may change in course of time affecting the values.',
                'We understand, bank may inspect the property and reserve the right to accept or reject this valuation report.',
            ],
            'inspection_date' => $input['inspection_date'],
            'comments' => $input['comments'] ?? null,
        ];
    }

    private function formatPct(float $pct): string
    {
        // "70" rather than "70.00" when the value is a whole number --
        // matches how every reference document actually writes it.
        return rtrim(rtrim(number_format($pct, 2), '0'), '.');
    }
}
