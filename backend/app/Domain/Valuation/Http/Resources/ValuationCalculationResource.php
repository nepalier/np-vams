<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ValuationCalculationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'valuation_assignment_id' => $this->valuation_assignment_id,
            'property_id' => $this->property_id,
            'method' => $this->method,
            'status' => $this->status,
            'computed_value' => $this->computed_value,
            'computed_details' => $this->computed_details,
            'calculated_at' => $this->calculated_at,
        ];
    }
}
