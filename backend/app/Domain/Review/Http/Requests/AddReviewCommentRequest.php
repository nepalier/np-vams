<?php

declare(strict_types=1);

namespace App\Domain\Review\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddReviewCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.view');
    }

    public function rules(): array
    {
        return [
            'section' => ['nullable', 'string', 'max:100'],
            'comment_type' => ['nullable', 'in:inline,calculation_validation,rate_verification,document_deficiency,risk_review'],
            'comment' => ['required', 'string', 'max:2000'],
            'severity' => ['nullable', 'in:information,warning,high_risk,blocking_error'],
        ];
    }
}
