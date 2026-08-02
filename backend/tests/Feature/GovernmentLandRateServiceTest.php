<?php

use App\Domain\MasterData\Models\AreaUnit;
use App\Domain\MasterData\Models\District;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\Rates\Models\GovernmentLandRate;
use App\Domain\Rates\Services\GovernmentLandRateService;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->fiscalYear = FiscalYear::where('is_current', true)->first();
    $this->district = District::first();
    $this->rateUnit = AreaUnit::first();
    $this->service = app(GovernmentLandRateService::class);
});

test('creating a rate starts at version 1 and is marked current', function () {
    $rate = $this->service->create([
        'fiscal_year_id' => $this->fiscalYear->id, 'district_id' => $this->district->id,
        'rate_unit_id' => $this->rateUnit->id, 'minimum_rate' => 500000, 'effective_date' => now()->toDateString(),
    ]);

    expect($rate->version)->toBe(1);
    expect($rate->is_current)->toBeTrue();
});

test('publishing a new version does NOT delete or mutate the old rate -- it marks it superseded and creates a genuinely new row', function () {
    $original = $this->service->create([
        'fiscal_year_id' => $this->fiscalYear->id, 'district_id' => $this->district->id,
        'rate_unit_id' => $this->rateUnit->id, 'minimum_rate' => 500000, 'effective_date' => now()->subMonths(3)->toDateString(),
    ]);

    $newVersion = $this->service->createNewVersion($original, [
        'rate_unit_id' => $this->rateUnit->id, 'minimum_rate' => 550000, 'effective_date' => now()->toDateString(),
    ]);

    // The original row still exists in the database, completely unaltered
    // in its own historical values -- only is_current and superseded_by_id changed.
    $original->refresh();
    expect($original->minimum_rate)->toEqual(500000.00);
    expect($original->is_current)->toBeFalse();
    expect($original->superseded_by_id)->toBe($newVersion->id);

    expect($newVersion->version)->toBe(2);
    expect($newVersion->is_current)->toBeTrue();
    expect($newVersion->minimum_rate)->toEqual(550000.00);
    expect(GovernmentLandRate::count())->toBe(2); // both rows genuinely exist, neither was deleted
});

test('findCurrentRate falls back progressively: ward match wins over local-level, which wins over district-only', function () {
    $localLevel = \App\Domain\MasterData\Models\LocalLevel::where('district_id', $this->district->id)->first();
    $ward = \App\Domain\MasterData\Models\Ward::where('local_level_id', $localLevel->id)->first();

    // District-only rate (broadest fallback)
    $this->service->create([
        'fiscal_year_id' => $this->fiscalYear->id, 'district_id' => $this->district->id,
        'rate_unit_id' => $this->rateUnit->id, 'minimum_rate' => 100000, 'effective_date' => now()->toDateString(),
        'approval_status' => 'approved',
    ]);

    // Ward-specific rate (most specific)
    $wardRate = $this->service->create([
        'fiscal_year_id' => $this->fiscalYear->id, 'district_id' => $this->district->id,
        'local_level_id' => $localLevel->id, 'ward_id' => $ward->id,
        'rate_unit_id' => $this->rateUnit->id, 'minimum_rate' => 300000, 'effective_date' => now()->toDateString(),
        'approval_status' => 'approved',
    ]);

    $found = $this->service->findCurrentRate($this->fiscalYear->id, $this->district->id, $localLevel->id, $ward->id, null);

    expect($found->id)->toBe($wardRate->id);
    expect((float) $found->minimum_rate)->toBe(300000.0);
});

test('a superseded (non-current) rate is never returned by the lookup, even if it would otherwise match', function () {
    $original = $this->service->create([
        'fiscal_year_id' => $this->fiscalYear->id, 'district_id' => $this->district->id,
        'rate_unit_id' => $this->rateUnit->id, 'minimum_rate' => 100000, 'effective_date' => now()->toDateString(),
        'approval_status' => 'approved',
    ]);

    $this->service->createNewVersion($original, [
        'rate_unit_id' => $this->rateUnit->id, 'minimum_rate' => 120000, 'effective_date' => now()->toDateString(),
        'approval_status' => 'approved',
    ]);

    $found = $this->service->findCurrentRate($this->fiscalYear->id, $this->district->id, null, null, null);

    expect((float) $found->minimum_rate)->toBe(120000.0); // the new version, not the superseded original
});
