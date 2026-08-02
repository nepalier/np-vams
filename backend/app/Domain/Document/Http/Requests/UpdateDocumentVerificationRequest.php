<?php

declare(strict_types=1);

namespace App\Domain\Document\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.update');
    }

    public function rules(): array
    {
        return [
            'original_seen' => ['nullable', 'boolean'],
            'copy_received' => ['nullable', 'boolean'],
            'online_verified' => ['nullable', 'boolean'],
            'authority_verified' => ['nullable', 'boolean'],
            'verification_status' => [
                'required',
                'in:received,original_seen,copy_received,online_verified,authority_verified,expired,incomplete,not_applicable,suspected_inconsistency,clarification_required,rejected',
            ],
            'verification_remarks' => ['nullable', 'string'],
        ];
    }
}
