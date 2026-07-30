<?php

declare(strict_types=1);

namespace App\Domain\Property\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLandParcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.create');
    }

    public function rules(): array
    {
        return [
            'kitta_number' => ['required', 'string', 'max:100'],
            'lalpurja_number' => ['nullable', 'string', 'max:100'],
            'land_category' => ['nullable', 'string', 'max:100'],
            'area_lalpurja' => ['nullable', 'numeric', 'min:0'],
            'area_lalpurja_unit_id' => ['required_with:area_lalpurja', 'integer', 'exists:area_units,id'],
            'area_site_measured' => ['nullable', 'numeric', 'min:0'],
            'area_site_measured_unit_id' => ['required_with:area_site_measured', 'integer', 'exists:area_units,id'],
            'area_considered_sqm' => ['nullable', 'numeric', 'min:0'],
            'four_boundaries' => ['nullable', 'array'],
            'boundary_points' => ['nullable', 'array', 'min:3'],
            'boundary_points.*.lat' => ['required_with:boundary_points', 'numeric', 'between:-90,90'],
            'boundary_points.*.lng' => ['required_with:boundary_points', 'numeric', 'between:-180,180'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
