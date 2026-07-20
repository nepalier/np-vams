<?php

declare(strict_types=1);

namespace App\Domain\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'client_id' => $this->client_id,
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'subtotal' => $this->subtotal,
            'vat_amount' => $this->vat_amount,
            'tds_amount' => $this->tds_amount,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'credited_amount' => $this->credited_amount,
            'outstanding_amount' => $this->outstanding_amount,
            'status' => $this->status,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_rate' => $item->unit_rate,
                'amount' => $item->amount,
            ])),
        ];
    }
}
