<?php

declare(strict_types=1);

namespace App\Domain\SiteVisit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.update');
    }

    public function rules(): array
    {
        return [
            'property_id' => ['nullable', 'uuid', 'exists:properties,id'],
            'scheduled_at' => ['required', 'date'],
        ];
    }
}
