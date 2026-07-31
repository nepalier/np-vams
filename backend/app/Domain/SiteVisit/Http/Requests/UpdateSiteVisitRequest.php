<?php

declare(strict_types=1);

namespace App\Domain\SiteVisit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.update');
    }

    public function rules(): array
    {
        return [
            'owner_representative_confirmed' => ['nullable', 'boolean'],
            'owner_representative_name' => ['nullable', 'string', 'max:150'],
            'field_checklist' => ['nullable', 'array'],
            'field_notes' => ['nullable', 'string'],
        ];
    }
}
