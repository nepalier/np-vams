<?php

declare(strict_types=1);

namespace App\Domain\Party\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuarantorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.create');
    }

    public function rules(): array
    {
        return [
            'party_kind' => ['required', 'in:individual,company,institutional,trust,guthi'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ne' => ['nullable', 'string', 'max:255'],
            'citizenship_number' => ['nullable', 'string', 'max:50'],
            'permanent_address' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'consent_for_inspection' => ['nullable', 'boolean'],
            'consent_for_data_processing' => ['nullable', 'boolean'],
        ];
    }
}
