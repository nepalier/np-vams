<?php

declare(strict_types=1);

namespace App\Domain\Comparable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComparablePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.update');
    }

    public function rules(): array
    {
        return [
            'property_type_id' => ['nullable', 'integer', 'exists:property_types,id'],
            'location' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'transaction_date' => ['nullable', 'date'],
            'offer_date' => ['nullable', 'date'],
            'parcel_area_sqm' => ['nullable', 'numeric', 'min:0'],
            'built_up_area_sqm' => ['nullable', 'numeric', 'min:0'],
            'road_width_m' => ['nullable', 'numeric', 'min:0'],
            'road_surface' => ['nullable', 'string', 'max:50'],
            'frontage_m' => ['nullable', 'numeric', 'min:0'],
            'shape' => ['nullable', 'string', 'max:50'],
            'land_use' => ['nullable', 'string', 'max:100'],
            'is_corner' => ['nullable', 'boolean'],
            'transaction_value' => ['nullable', 'numeric', 'min:0'],
            'asking_value' => ['nullable', 'numeric', 'min:0'],
            'verified_value' => ['nullable', 'numeric', 'min:0'],
            'unit_rate' => ['required', 'numeric', 'min:0'],
            'data_source' => ['nullable', 'string', 'max:150'],
            'contact_source' => ['nullable', 'string', 'max:150'],
            'verification_status' => ['nullable', 'string', 'max:50'],
            // Section 21's own grade definitions:
            // A = verified registered transaction, B = verified institutional
            // transaction, C = multiple confirmed quotations, D = single
            // quotation, E = unverified asking price.
            'reliability_grade' => ['required', 'in:A,B,C,D,E'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
