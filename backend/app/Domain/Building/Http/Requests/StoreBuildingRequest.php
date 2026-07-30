<?php

declare(strict_types=1);

namespace App\Domain\Building\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.create');
    }

    public function rules(): array
    {
        return [
            'building_name' => ['nullable', 'string', 'max:255'],
            'building_type' => ['nullable', 'string', 'max:100'],
            'number_of_floors' => ['required', 'integer', 'min:1'],
            'basement_floors' => ['nullable', 'integer', 'min:0'],
            'construction_year_bs' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'current_use' => ['nullable', 'string', 'max:100'],
            'structural_system' => ['nullable', 'in:rcc_frame,load_bearing_masonry,steel,prefab,timber,traditional_masonry,adobe,mixed'],
            'foundation_type' => ['nullable', 'string', 'max:100'],
            'overall_condition' => ['nullable', 'string', 'max:50'],
            // Optional nested floor creation in the same request -- same
            // pattern as AssignmentController accepting property_ids and
            // BuildingConditionAssessmentController accepting items.
            'floors' => ['nullable', 'array'],
            'floors.*.floor_name' => ['required_with:floors', 'string', 'max:100'],
            'floors.*.floor_number' => ['required_with:floors', 'integer'],
            'floors.*.covered_area_sqm' => ['nullable', 'numeric', 'min:0'],
            'floors.*.floor_use' => ['nullable', 'string', 'max:100'],
        ];
    }
}
