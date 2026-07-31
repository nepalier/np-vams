<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuildingCostEstimationRequest extends FormRequest
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
            'age_years' => ['required', 'numeric', 'min:0'],
            'floors' => ['required', 'array', 'min:1'],
            'floors.*.floor_name' => ['required', 'string', 'max:100'],
            'floors.*.area' => ['required', 'numeric', 'min:0'],
            'floors.*.rate_per_unit_area' => ['required', 'numeric', 'min:0'],
            // Optional overrides for THIS calculation only -- if omitted,
            // resolution falls to the tenant's configured Settings
            // defaults, then the engine's own 5%/5%/2% hard default.
            'sanitary_fixture_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'electrical_fixture_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'depreciation_pct_per_annum' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
