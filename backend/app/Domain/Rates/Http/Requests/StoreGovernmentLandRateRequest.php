<?php

declare(strict_types=1);

namespace App\Domain\Rates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGovernmentLandRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Government rate data affects every valuation calculation
        // tenant-wide -- restricted to admin roles, same reasoning as
        // Settings.
        return $this->user()->hasAnyRole(['Tenant Administrator', 'Valuation Firm Administrator']);
    }

    public function rules(): array
    {
        return [
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'local_level_id' => ['nullable', 'integer', 'exists:local_levels,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'location' => ['nullable', 'string', 'max:150'],
            'road' => ['nullable', 'string', 'max:150'],
            'land_category' => ['nullable', 'string', 'max:100'],
            'rate_unit_id' => ['required', 'integer', 'exists:area_units,id'],
            'minimum_rate' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
            'source_document' => ['nullable', 'string', 'max:255'],
            'approval_status' => ['nullable', 'in:pending,approved,rejected'],
        ];
    }
}
