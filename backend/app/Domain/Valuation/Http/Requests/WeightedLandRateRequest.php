<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WeightedLandRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('valuations.create');
    }

    public function rules(): array
    {
        return [
            'property_id' => ['nullable', 'uuid', 'exists:properties,id'],
            'plots' => ['required', 'array', 'min:1'],
            'plots.*.plot_label' => ['required', 'string', 'max:100'],
            'plots.*.area_considered' => ['required', 'numeric', 'min:0'],
            'plots.*.government_rate' => ['required', 'numeric', 'min:0'],
            'plots.*.market_rate' => ['required', 'numeric', 'min:0'],
        ];
    }
}
