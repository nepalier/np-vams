<?php

declare(strict_types=1);

namespace App\Domain\Property\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.update');
    }

    public function rules(): array
    {
        return [
            'property_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'property_type_id' => ['nullable', 'integer', 'exists:property_types,id'],
            'property_use' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'local_level_id' => ['nullable', 'integer', 'exists:local_levels,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
