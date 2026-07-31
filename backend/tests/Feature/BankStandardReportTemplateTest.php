<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Domain\Party\Models\Borrower;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Services\ReportGenerationService;
use App\Domain\Valuation\Models\ValuationCalculation;
use App\Domain\Valuation\Models\ValuationReconciliation;
use App\Models\Tenant;
use Database\Seeders\MasterDataSeeder;

/**
 * The most important test in this batch: actually RENDERS the new Blade
 * template through DomPDF with real data, including both optional
 * sections (land rate table, building cost table) present. A Blade
 * @if/@foreach imbalance or an undefined array key deep in
 * computed_details would only ever surface at render time, not from a
 * static read-through of the template file -- this is what catches that.
 */
beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->client = Client::create([
        'tenant_id' => $this->tenant->id, 'name_en' => 'Everest Bank', 'client_type' => 'commercial_bank',
        'land_rate_government_weight_pct' => 30, 'land_rate_market_weight_pct' => 70, 'distress_value_pct' => 80,
    ]);

    $this->borrower = Borrower::create([
        'tenant_id' => $this->tenant->id, 'party_kind' => 'individual', 'name_en' => 'Ramesh Sharma',
        'citizenship_number' => '12-01-70-00001', 'permanent_address' => 'Kathmandu', 'mobile' => '9800000001',
    ]);

    $this->assignment = ValuationAssignment::create([
        'tenant_id' => $this->tenant->id, 'assignment_number' => 'VAL-2082-000099',
        'fiscal_year_id' => FiscalYear::where('is_current', true)->first()->id, 'client_id' => $this->client->id,
        'borrower_id' => $this->borrower->id, 'assignment_date' => now(), 'priority' => 'normal',
        'valuation_purpose_id' => ValuationPurpose::first()->id, 'status' => 'draft',
    ]);

    $this->report = Report::create([
        'tenant_id' => $this->tenant->id, 'valuation_assignment_id' => $this->assignment->id, 'status' => 'drafting',
    ]);

    // Real land-rate calculation, same numbers as the verified JBBL test case.
    ValuationCalculation::create([
        'tenant_id' => $this->tenant->id, 'valuation_assignment_id' => $this->assignment->id,
        'method' => 'weighted_land_rate', 'status' => 'draft',
        'input_snapshot' => [], 'computed_value' => 3_280_055.0,
        'computed_details' => [
            'government_weight_pct' => 30.0, 'market_weight_pct' => 70.0,
            'plots' => [
                ['plot_label' => 'Front', 'area_considered' => 0.472, 'government_rate' => 2_300_000, 'market_rate' => 6_500_000, 'government_component' => 690_000, 'market_component' => 4_550_000, 'weighted_rate' => 5_240_000, 'plot_value' => 2_473_280],
            ],
            'total_land_value' => 3_280_055.0,
        ],
        'calculated_at' => now(),
    ]);

    // Real building-cost calculation, same numbers as the verified JBBL test case.
    ValuationCalculation::create([
        'tenant_id' => $this->tenant->id, 'valuation_assignment_id' => $this->assignment->id,
        'method' => 'building_cost_estimation', 'status' => 'draft',
        'input_snapshot' => [], 'computed_value' => 2_174_581.02,
        'computed_details' => [
            'floors' => [['floor_name' => 'Ground Floor', 'area' => 802.31, 'rate_per_unit_area' => 2800, 'civil_works_cost' => 2_246_468.00]],
            'total_civil_works_cost' => 2_246_468.00, 'sanitary_fixture_pct' => 5.0, 'sanitary_cost' => 112_323.40,
            'electrical_fixture_pct' => 5.0, 'electrical_cost' => 112_323.40, 'total_fixture_cost' => 224_646.80,
            'cost_of_building_and_fixture' => 2_471_114.80, 'age_years' => 6.0, 'depreciation_pct_per_annum' => 2.0,
            'total_depreciation_pct' => 12.0, 'depreciation_amount' => 296_533.78, 'actual_construction_cost' => 2_174_581.02,
        ],
        'calculated_at' => now(),
    ]);

    $this->reconciliation = ValuationReconciliation::create([
        'tenant_id' => $this->tenant->id, 'valuation_assignment_id' => $this->assignment->id,
        'method_inputs' => [
            ['method' => 'weighted_land_rate', 'value' => 3_280_055.0, 'reliability_rating' => 4],
            ['method' => 'building_cost_estimation', 'value' => 2_174_581.02, 'reliability_rating' => 4],
        ],
        'reconciled_market_value' => 5_454_636.02, 'rounded_market_value' => 5_450_000.0,
        'distress_value' => 4_363_708.82,
    ]);

    $this->service = app(ReportGenerationService::class);
});

test('the bank_standard_np template renders successfully with both land and building sections present', function () {
    $pdfBytes = $this->service->renderPdf(
        $this->report, $this->assignment->fresh(), $this->reconciliation, $this->reconciliation->method_inputs, 1,
        template: 'bank_standard_np',
    );

    expect($pdfBytes)->not->toBeEmpty();
    expect(substr($pdfBytes, 0, 4))->toBe('%PDF'); // a genuine PDF was produced, not an error page or empty string
});

test('the template correctly surfaces the amount-in-words conversion for the reconciled value', function () {
    $certificateSummary = app(\App\Domain\Valuation\Services\ValuationCertificateSummaryService::class)->generate([
        'weighted_fair_market_value' => (float) $this->reconciliation->reconciled_market_value,
        'government_weight_pct' => 30.0, 'market_weight_pct' => 70.0, 'distress_value_pct' => 80.0,
        'inspection_date' => now()->toDateString(), 'comments' => null,
    ]);

    $html = view('reports.templates.bank_standard_np', [
        'report' => $this->report, 'assignment' => $this->assignment->fresh(), 'organization' => null,
        'client' => $this->client, 'borrower' => $this->borrower, 'valuationPurpose' => 'Mortgage',
        'properties' => collect(), 'reconciliation' => $this->reconciliation,
        'methodResults' => $this->reconciliation->method_inputs, 'versionNumber' => 1,
        'signerName' => null, 'signerLicenseNumber' => null, 'riskCategory' => null,
        'landRateCalculation' => null, 'buildingCostCalculation' => null,
        'certificateSummary' => $certificateSummary, 'locale' => 'en',
    ])->render();

    expect($html)->toContain('30% Gov. + 70% Market');
    expect($html)->toContain('Five Million Four Hundred Fifty Four Thousand Six Hundred Thirty Six Rupees only');
});

test('the template does not error when there is no land or building calculation at all -- both optional sections cleanly absent', function () {
    ValuationCalculation::where('valuation_assignment_id', $this->assignment->id)->delete();

    $pdfBytes = $this->service->renderPdf(
        $this->report, $this->assignment->fresh(), $this->reconciliation, $this->reconciliation->method_inputs, 1,
        template: 'bank_standard_np',
    );

    expect(substr($pdfBytes, 0, 4))->toBe('%PDF');
});
