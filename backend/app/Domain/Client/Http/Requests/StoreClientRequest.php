<?php

declare(strict_types=1);

namespace App\Domain\Client\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.create'); // creating a client is a precursor to creating assignments for them
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ne' => ['nullable', 'string', 'max:255'],
            'client_type' => ['required', 'in:commercial_bank,development_bank,finance_company,microfinance,cooperative,insurance,government_agency,corporate,individual,other'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'pan_number' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'authorized_contact_person' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
