<?php

declare(strict_types=1);

namespace App\Domain\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invoices.create'); // same financial-authoring permission as invoices
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'commission_type' => ['required', 'in:percentage,fixed'],
            'commission_rate_pct' => ['required_if:commission_type,percentage', 'nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount' => ['required_if:commission_type,fixed', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
