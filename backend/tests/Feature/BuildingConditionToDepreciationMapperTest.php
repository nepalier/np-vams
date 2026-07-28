<?php

use App\Domain\Building\Models\Building;
use App\Domain\Building\Models\BuildingConditionAssessment;
use App\Domain\Building\Models\BuildingConditionAssessmentItem;
use App\Domain\Building\Services\BuildingConditionToDepreciationMapper;
use App\Domain\Property\Models\Property;
use App\Domain\Valuation\Services\CostApproachEngine;
use App\Models\Tenant;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->property = Property::create(['tenant_id' => $this->tenant->id, 'property_name' => 'Test Property']);
    $this->building = Building::create(['tenant_id' => $this->tenant->id, 'property_id' => $this->property->id]);

    $this->mapper = new BuildingConditionToDepreciationMapper;
});

test('good structural condition (rating 1) maps to a low suggested depreciation percentage', function () {
    $assessment = BuildingConditionAssessment::create(['tenant_id' => $this->tenant->id, 'building_id' => $this->building->id]);

    foreach (['foundation', 'columns', 'walls', 'roof'] as $type) {
        BuildingConditionAssessmentItem::create([
            'tenant_id' => $this->tenant->id, 'building_condition_assessment_id' => $assessment->id,
            'item_type' => $type, 'condition_rating' => 1,
        ]);
    }

    $result = $this->mapper->map($assessment->fresh()->load('items'));

    expect($result['physical_depreciation_pct'])->toBe(5.0);
    expect($result['items_considered'])->toBe(4);
});

test('poor structural condition (rating 5) maps to a high suggested depreciation percentage', function () {
    $assessment = BuildingConditionAssessment::create(['tenant_id' => $this->tenant->id, 'building_id' => $this->building->id]);

    foreach (['foundation', 'cracks', 'settlement'] as $type) {
        BuildingConditionAssessmentItem::create([
            'tenant_id' => $this->tenant->id, 'building_condition_assessment_id' => $assessment->id,
            'item_type' => $type, 'condition_rating' => 5,
        ]);
    }

    $result = $this->mapper->map($assessment->fresh()->load('items'));

    expect($result['physical_depreciation_pct'])->toBe(75.0);
});

test('mixed ratings average correctly and interpolate between configured points', function () {
    $assessment = BuildingConditionAssessment::create(['tenant_id' => $this->tenant->id, 'building_id' => $this->building->id]);

    BuildingConditionAssessmentItem::create([
        'tenant_id' => $this->tenant->id, 'building_condition_assessment_id' => $assessment->id,
        'item_type' => 'foundation', 'condition_rating' => 2,
    ]);
    BuildingConditionAssessmentItem::create([
        'tenant_id' => $this->tenant->id, 'building_condition_assessment_id' => $assessment->id,
        'item_type' => 'walls', 'condition_rating' => 3,
    ]);

    $result = $this->mapper->map($assessment->fresh()->load('items'));

    // average rating = 2.5 -> interpolated between rating 2 (15%) and rating 3 (30%) = 22.5%
    expect($result['physical_depreciation_pct'])->toBe(22.5);
});

test('functional and economic obsolescence ratings are reported separately from the structural physical percentage', function () {
    $assessment = BuildingConditionAssessment::create(['tenant_id' => $this->tenant->id, 'building_id' => $this->building->id]);

    BuildingConditionAssessmentItem::create([
        'tenant_id' => $this->tenant->id, 'building_condition_assessment_id' => $assessment->id,
        'item_type' => 'foundation', 'condition_rating' => 1,
    ]);
    BuildingConditionAssessmentItem::create([
        'tenant_id' => $this->tenant->id, 'building_condition_assessment_id' => $assessment->id,
        'item_type' => 'functional_obsolescence', 'condition_rating' => 4,
    ]);

    $result = $this->mapper->map($assessment->fresh()->load('items'));

    expect($result['physical_depreciation_pct'])->toBe(5.0); // unaffected by the obsolescence item
    expect($result['functional_obsolescence_rating'])->toBe(4.0);
});

test('the mapped depreciation percentage feeds directly into CostApproachEngine and produces a real depreciated value', function () {
    $assessment = BuildingConditionAssessment::create(['tenant_id' => $this->tenant->id, 'building_id' => $this->building->id]);

    foreach (['foundation', 'walls'] as $type) {
        BuildingConditionAssessmentItem::create([
            'tenant_id' => $this->tenant->id, 'building_condition_assessment_id' => $assessment->id,
            'item_type' => $type, 'condition_rating' => 2, // -> 15%
        ]);
    }

    $suggested = $this->mapper->map($assessment->fresh()->load('items'));

    $result = app(CostApproachEngine::class)->calculate([
        'built_up_area_sqm' => 100,
        'base_construction_rate' => 10000, // RCN = 1,000,000
        'depreciation_method' => 'observed_condition',
        'physical_depreciation_pct' => $suggested['physical_depreciation_pct'],
    ]);

    expect($result['physical_depreciation_amount'])->toBe(150000.0); // 1,000,000 * 15%
    expect($result['depreciated_value'])->toBe(850000.0);
});
