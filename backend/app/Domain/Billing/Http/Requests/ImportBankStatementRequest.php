<?php

declare(strict_types=1);

namespace App\Domain\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportBankStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invoices.record_payment');
    }

    public function rules(): array
    {
        return [
            // Expected columns, in order: transaction_date,description,reference_number,amount
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }
}
