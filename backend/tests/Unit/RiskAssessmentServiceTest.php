<?php

use App\Domain\Risk\Services\RiskAssessmentService;

beforeEach(function () {
    $this->service = new RiskAssessmentService;
    $this->bands = [
        ['min_score' => 0, 'max_score' => 10, 'category' => 'low'],
        ['min_score' => 10.01, 'max_score' => 25, 'category' => 'moderate'],
        ['min_score' => 25.01, 'max_score' => 45, 'category' => 'high'],
        ['min_score' => 45.01, 'max_score' => 9999, 'category' => 'unacceptable'],
    ];
});

test('score is the sum of applied indicator weights', function () {
    $result = $this->service->assess([
        ['code' => 'no_road_access', 'weight' => 8],
        ['code' => 'ownership_dispute', 'weight' => 10],
    ], $this->bands);

    expect($result['computed_score'])->toBe(18.0);
    expect($result['computed_category'])->toBe('moderate');
});

test('a property with no indicators present scores as low risk', function () {
    $result = $this->service->assess([], $this->bands);

    expect($result['computed_score'])->toBe(0.0);
    expect($result['computed_category'])->toBe('low');
});

test('a heavily flagged property is scored unacceptable', function () {
    $result = $this->service->assess([
        ['code' => 'court_case', 'weight' => 10],
        ['code' => 'ownership_dispute', 'weight' => 10],
        ['code' => 'landslide_exposure', 'weight' => 8],
        ['code' => 'public_acquisition_notice', 'weight' => 9],
        ['code' => 'unauthorized_construction', 'weight' => 7],
        ['code' => 'unclear_boundary', 'weight' => 6],
    ], $this->bands);

    expect($result['computed_category'])->toBe('unacceptable');
});

test('throws if no configured band covers the computed score, rather than silently defaulting', function () {
    $this->service->assess(
        [['code' => 'x', 'weight' => 100]],
        [['min_score' => 0, 'max_score' => 10, 'category' => 'low']] // gap above 10 — 100 is uncovered
    );
})->throws(InvalidArgumentException::class);

test('a category override without justification is rejected', function () {
    $this->service->applyOverride('low', 'high', null);
})->throws(InvalidArgumentException::class);

test('a justified override replaces the computed category', function () {
    $result = $this->service->applyOverride('low', 'high', 'Site visit revealed undisclosed structural cracking.');

    expect($result['final_category'])->toBe('high');
    expect($result['is_overridden'])->toBeTrue();
});

test('no override recorded when the override category matches the computed one', function () {
    $result = $this->service->applyOverride('moderate', 'moderate', null);

    expect($result['is_overridden'])->toBeFalse();
});
