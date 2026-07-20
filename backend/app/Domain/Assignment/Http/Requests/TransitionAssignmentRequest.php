<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransitionAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // fine-grained role check happens inside WorkflowEngine against the configured rule
    }

    public function rules(): array
    {
        return [
            'to_status' => ['required', 'string', 'exists:workflow_statuses,code'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
