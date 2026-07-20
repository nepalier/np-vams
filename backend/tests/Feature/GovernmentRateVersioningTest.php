<?php

use App\Domain\MasterData\Models\Province;
use App\Domain\MasterData\Models\District;
use App\Domain\MasterData\Models\AreaUnit;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\Rates\Models\GovernmentLandRate;
use App\Domain\Rates\Services\RateVersioningService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\NepalGeoSeeder;

beforeEach(function () {
    $this->seed(NepalGeoSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->district = District::first();
    $this->fiscalYear = FiscalYear::where('is_current', true)->first();
    $this->sqm = AreaUnit::where('code', 'sqm')->first();

    $this->original = GovernmentLandRate::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'district_id' => $this->district->id,
        'rate_unit_id' => $this->sqm->id,
        'minimum_rate' => 50000,
        'effective_date' => now(),
        'version' => 1,
        'is_current' => true,
        'approval_status' => 'approved',
    ]);
});

test('revising a government rate creates a new row rather than mutating the original', function () {
    $originalId = $this->original->id;
    $originalRate = $this->original->minimum_rate;

    $revised = app(RateVersioningService::class)->reviseGovernmentRate($this->original, ['minimum_rate' => 55000]);

    $this->original->refresh();

    expect($revised->id)->not->toBe($originalId);
    expect($revised->version)->toBe(2);
    expect($revised->minimum_rate)->toBe('55000.00');
    expect($revised->is_current)->toBeTrue();

    // The original row is preserved exactly as it was, just marked superseded.
    expect($this->original->minimum_rate)->toBe($originalRate);
    expect($this->original->is_current)->toBeFalse();
    expect($this->original->superseded_by_id)->toBe($revised->id);
});

test('the historical fiscal-year rate remains queryable after a revision', function () {
    app(RateVersioningService::class)->reviseGovernmentRate($this->original, ['minimum_rate' => 60000]);

    $historyCount = GovernmentLandRate::where('district_id', $this->district->id)
        ->where('fiscal_year_id', $this->fiscalYear->id)
        ->count();

    expect($historyCount)->toBe(2); // original + revision, both still present
});
