<?php

declare(strict_types=1);

namespace App\Domain\Property\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLandParcelCharacteristicsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.update');
    }

    public function rules(): array
    {
        return [
            'plot_shape' => ['nullable', 'string', 'max:50'],
            'frontage_m' => ['nullable', 'numeric', 'min:0'],
            'average_depth_m' => ['nullable', 'numeric', 'min:0'],
            'number_of_road_frontages' => ['nullable', 'integer', 'min:0'],
            'is_corner_plot' => ['nullable', 'boolean'],
            'topography' => ['nullable', 'in:flat,gentle_slope,steep_slope,undulating'],
            'slope_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'flood_exposure' => ['nullable', 'in:none,low,moderate,high'],
            'landslide_exposure' => ['nullable', 'in:none,low,moderate,high'],
            'access_type' => ['nullable', 'in:motorable,foot_trail,no_direct_access'],
            'road_width_m' => ['nullable', 'numeric', 'min:0'],
            'road_surface' => ['nullable', 'string', 'max:50'],
            'motorable_access' => ['nullable', 'boolean'],
            'marketability_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'saleability_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'neighbourhood_quality_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
