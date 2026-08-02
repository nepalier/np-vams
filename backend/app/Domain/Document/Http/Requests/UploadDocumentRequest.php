<?php

declare(strict_types=1);

namespace App\Domain\Document\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.update');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:25600'], // 25MB, matches DocumentService::MAX_SIZE_BYTES
            'category' => ['required', 'in:land,building,identity_organizational'],
            'document_type' => ['required', 'string', 'max:100'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'issuing_authority' => ['nullable', 'string', 'max:150'],
        ];
    }
}
