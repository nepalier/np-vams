<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CostApproachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('valuations.create');
    }

    public function rules(): array
    {
        return [
            'property_id' => ['nullable', 'uuid', 'exists:properties,id'],
            'building_id' => ['nullable', 'uuid', 'exists:buildings,id'],
            'built_up_area_sqm' => ['required', 'numeric', 'min:0'],
            'base_construction_rate' => ['required', 'numeric', 'min:0'],
            'location_factor' => ['nullable', 'numeric', 'min:0'],
            'transportation_factor' => ['nullable', 'numeric', 'min:0'],
            'material_factor' => ['nullable', 'numeric', 'min:0'],
            'labour_factor' => ['nullable', 'numeric', 'min:0'],
            'professional_fee_pct' => ['nullable', 'numeric', 'min:0'],
            'external_works_amount' => ['nullable', 'numeric', 'min:0'],
            'service_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'completion_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'depreciation_method' => ['required', 'in:straight_line,age_life,observed_condition,component_wise,custom_professional'],
            'age_years' => ['required_if:depreciation_method,straight_line,age_life', 'numeric', 'min:0'],
            'economic_life_years' => ['required_if:depreciation_method,straight_line,age_life', 'numeric', 'min:1'],
            'physical_depreciation_pct' => ['required_if:depreciation_method,observed_condition,custom_professional', 'numeric', 'min:0'],
            'components' => ['required_if:depreciation_method,component_wise', 'array'],
            'functional_obsolescence_amount' => ['nullable', 'numeric', 'min:0'],
            'economic_obsolescence_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
