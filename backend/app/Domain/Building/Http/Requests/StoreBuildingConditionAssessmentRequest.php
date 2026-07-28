<?php

declare(strict_types=1);

namespace App\Domain\Building\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBuildingConditionAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.update');
    }

    public function rules(): array
    {
        return [
            'structural_risk' => ['nullable', 'in:low,moderate,high,critical'],
            'required_repairs' => ['nullable', 'string'],
            'repair_cost_estimate' => ['nullable', 'numeric', 'min:0'],
            'remaining_life_years' => ['nullable', 'integer', 'min:0'],
            'overall_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => [
                'required',
                'in:foundation,columns,beams,slabs,walls,cracks,settlement,dampness,roof,doors,windows,electrical,plumbing,sanitation,fire_safety,lift,hvac,maintenance,functional_obsolescence,economic_obsolescence',
            ],
            'items.*.condition_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'items.*.remarks' => ['nullable', 'string'],
        ];
    }
}
