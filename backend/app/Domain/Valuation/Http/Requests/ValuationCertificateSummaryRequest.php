<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValuationCertificateSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('valuations.create');
    }

    public function rules(): array
    {
        return [
            'weighted_fair_market_value' => ['nullable', 'numeric', 'min:0'], // falls back to the assignment's latest weighted_land_rate calculation if omitted
            'government_weight_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'market_weight_pct' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_with:government_weight_pct'],
            'distress_value_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'inspection_date' => ['required', 'date'],
            'comments' => ['nullable', 'string'],
        ];
    }
}
