<?php

declare(strict_types=1);

namespace App\Domain\Report\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SignReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Approving Authority');
    }

    public function rules(): array
    {
        return [
            'signer_name' => ['nullable', 'string', 'max:150'],
            'signer_license_number' => ['nullable', 'string', 'max:100'],
            'certificate_serial' => ['nullable', 'string', 'max:150'],
            'certificate_issuer' => ['nullable', 'string', 'max:150'],
            'certificate_valid_from' => ['nullable', 'date'],
            'certificate_valid_until' => ['nullable', 'date', 'after:certificate_valid_from'],
        ];
    }
}
