<?php

use App\Domain\Valuation\Services\ValuationCertificateSummaryService;
use App\Support\Text\AmountToWordsConverter;

beforeEach(function () {
    $this->service = new ValuationCertificateSummaryService(new AmountToWordsConverter);
});

test('the FMV label reflects the ACTUAL percentages used, not a hard-coded 70/30 string', function () {
    // Using the 20/80 split from the reference Excel format, not the JBBL 30/70 default.
    $summary = $this->service->generate([
        'weighted_fair_market_value' => 13_450_000,
        'government_weight_pct' => 20,
        'market_weight_pct' => 80,
        'distress_value_pct' => 80,
        'inspection_date' => '2026-01-15',
        'comments' => null,
    ]);

    expect($summary['weighted_fair_market_value_label'])->toBe('Weighted Fair Market Value of the property (20% Gov. + 80% Market)');
});

test('distress value is correctly computed as the configured percentage of FMV, matching the real Excel reference figures', function () {
    // Reference Excel: FMV 13,450,000 (Total, Say) -> Distress 10,760,000 (80% of FMV)
    $summary = $this->service->generate([
        'weighted_fair_market_value' => 13_450_000,
        'government_weight_pct' => 20,
        'market_weight_pct' => 80,
        'distress_value_pct' => 80,
        'inspection_date' => '2026-01-15',
        'comments' => null,
    ]);

    expect($summary['distress_value'])->toBe(10_760_000.0);
    expect($summary['distress_value_in_words'])->toBe('Ten Million Seven Hundred Sixty Thousand Rupees only');
});

test('percentages that are whole numbers are formatted without trailing decimals, matching how reference documents write them', function () {
    $summary = $this->service->generate([
        'weighted_fair_market_value' => 1_000_000,
        'government_weight_pct' => 30.0,
        'market_weight_pct' => 70.0,
        'distress_value_pct' => 80.0,
        'inspection_date' => '2026-01-15',
        'comments' => null,
    ]);

    expect($summary['weighted_fair_market_value_label'])->toContain('30% Gov. + 70% Market');
    expect($summary['distress_value_label'])->toContain('80% of FMV');
});

test('the five standard declarations appear in the correct order, matching the reference document verbatim', function () {
    $summary = $this->service->generate([
        'weighted_fair_market_value' => 1_000_000, 'government_weight_pct' => 30, 'market_weight_pct' => 70,
        'distress_value_pct' => 80, 'inspection_date' => '2026-01-15', 'comments' => null,
    ]);

    expect($summary['declarations'])->toHaveCount(5);
    expect($summary['declarations'][0])->toContain('physically inspected');
    expect($summary['declarations'][4])->toContain('accept or reject');
});
