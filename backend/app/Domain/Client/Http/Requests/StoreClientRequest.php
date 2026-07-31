<?php

declare(strict_types=1);

namespace App\Domain\Client\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.create'); // creating a client is a precursor to creating assignments for them
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ne' => ['nullable', 'string', 'max:255'],
            'client_type' => ['required', 'in:commercial_bank,development_bank,finance_company,microfinance,cooperative,insurance,government_agency,corporate,individual,other'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'pan_number' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'authorized_contact_person' => ['nullable', 'string', 'max:150'],
            // Section-49-honouring configurability, not a system-wide
            // hard-coded default: different banks use genuinely different
            // conventions (30/70, 70/30, 20/80 government/market splits
            // have all been observed across real reference documents).
            'land_rate_government_weight_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'land_rate_market_weight_pct' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_with:land_rate_government_weight_pct'],
            'distress_value_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $govt = $this->input('land_rate_government_weight_pct');
            $market = $this->input('land_rate_market_weight_pct');

            if ($govt !== null && $market !== null && round((float) $govt + (float) $market, 2) !== 100.0) {
                $validator->errors()->add('land_rate_market_weight_pct', 'Government and market weight percentages must sum to 100.');
            }
        });
    }
}
