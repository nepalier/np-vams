<?php

declare(strict_types=1);

namespace App\Domain\SiteVisit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadSitePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.update');
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'], // 15MB
            'category' => [
                'required',
                'in:access_road,front_view,rear_view,left_view,right_view,boundary,floor,internal_room,staircase,kitchen,toilet,roof,utility_system,structural_defect,neighbourhood,gps_evidence,document_evidence,other',
            ],
            'site_visit_id' => ['nullable', 'uuid', 'exists:site_visits,id'],
            'property_id' => ['nullable', 'uuid', 'exists:properties,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'assignment_number' => ['required', 'string'],
            'property_code' => ['nullable', 'string'],
        ];
    }
}
