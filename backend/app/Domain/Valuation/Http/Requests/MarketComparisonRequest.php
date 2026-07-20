<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarketComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('valuations.create');
    }

    public function rules(): array
    {
        return [
            'property_id' => ['nullable', 'uuid', 'exists:properties,id'],
            'comparables' => ['required', 'array', 'min:1'],
            'comparables.*.base_rate' => ['required', 'numeric', 'min:0'],
            'comparables.*.weight' => ['nullable', 'numeric', 'min:0'],
            'comparables.*.comparable_property_id' => ['nullable', 'uuid', 'exists:comparable_properties,id'],
            'comparables.*.factors' => ['required', 'array', 'min:1'],
            'comparables.*.factors.*' => ['numeric', 'min:0'],
        ];
    }
}
