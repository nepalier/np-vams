<?php

declare(strict_types=1);

namespace App\Domain\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Org-wide defaults affecting every valuer's calculations --
        // restricted to admin roles, not the general assignments.update
        // permission everything else in Settings uses.
        return $this->user()->hasAnyRole(['Tenant Administrator', 'Valuation Firm Administrator']);
    }

    public function rules(): array
    {
        return [
            'default_land_rate_government_weight_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_land_rate_market_weight_pct' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_with:default_land_rate_government_weight_pct'],
            'default_distress_value_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_vehicle_scrap_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_vehicle_depreciation_pct_per_annum' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_vehicle_other_cost_pct_per_annum' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_building_sanitary_fixture_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_building_electrical_fixture_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_building_depreciation_pct_per_annum' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $govt = $this->input('default_land_rate_government_weight_pct');
            $market = $this->input('default_land_rate_market_weight_pct');

            if ($govt !== null && $market !== null && round((float) $govt + (float) $market, 2) !== 100.0) {
                $validator->errors()->add('default_land_rate_market_weight_pct', 'Government and market weight percentages must sum to 100.');
            }
        });
    }
}
