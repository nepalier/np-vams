<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.create');
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
            'client_branch_id' => ['nullable', 'uuid', 'exists:client_branches,id'],
            'assignment_date' => ['required', 'date'],
            'requested_completion_date' => ['nullable', 'date', 'after_or_equal:assignment_date'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'valuation_purpose_id' => ['required', 'integer', 'exists:valuation_purposes,id'],
            'loan_application_number' => ['nullable', 'string', 'max:100'],
            'borrower_id' => ['nullable', 'uuid', 'exists:borrowers,id'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'requested_loan_amount' => ['nullable', 'numeric', 'min:0'],
            'assignment_fee' => ['nullable', 'numeric', 'min:0'],
            'travel_fee' => ['nullable', 'numeric', 'min:0'],
            'additional_charges' => ['nullable', 'numeric', 'min:0'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'client_remarks' => ['nullable', 'string'],
            'internal_remarks' => ['nullable', 'string'],
            'property_ids' => ['required', 'array', 'min:1'],
            'property_ids.*' => ['uuid', 'exists:properties,id'],
        ];
    }
}
