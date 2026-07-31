<?php

declare(strict_types=1);

namespace App\Domain\SiteVisit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckInSiteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.update');
    }

    public function rules(): array
    {
        return [
            'check_in_latitude' => ['required', 'numeric', 'between:-90,90'],
            'check_in_longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
