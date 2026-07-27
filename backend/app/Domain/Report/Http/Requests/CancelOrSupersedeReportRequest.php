<?php

declare(strict_types=1);

namespace App\Domain\Report\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrSupersedeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Approving Authority');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
