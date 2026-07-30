<?php

declare(strict_types=1);

namespace App\Domain\Property\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.create');
    }

    public function rules(): array
    {
        return [
            'property_name' => ['nullable', 'string', 'max:255'],
            'property_type_id' => ['nullable', 'integer', 'exists:property_types,id'],
            'property_use' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'local_level_id' => ['nullable', 'integer', 'exists:local_levels,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'tole' => ['nullable', 'string', 'max:150'],
            'road_name' => ['nullable', 'string', 'max:150'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'area_classification' => ['nullable', 'in:urban,semi_urban,rural'],
        ];
    }
}
