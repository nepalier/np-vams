<?php

declare(strict_types=1);

namespace App\Domain\Review\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role enforcement happens in SegregationOfDutiesChecker + WorkflowEngine's allowed_roles
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
