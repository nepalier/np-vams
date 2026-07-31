<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleValuationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('valuations.create');
    }

    public function rules(): array
    {
        return [
            'current_market_price_of_new' => ['required', 'numeric', 'min:0'],
            'age_years' => ['required', 'numeric', 'min:0'],
            'other_reducing_factors' => ['nullable', 'numeric', 'min:0'],
            // Optional overrides for THIS calculation only -- if omitted,
            // resolution falls to the tenant's configured Settings
            // defaults, then the engine's own 10%/10%/2% hard default.
            'scrap_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'depreciation_pct_per_annum' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'other_cost_pct_per_annum' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
