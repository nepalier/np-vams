<?php

declare(strict_types=1);

namespace App\Domain\Client\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_ne' => $this->name_ne,
            'client_type' => $this->client_type,
            'registration_number' => $this->registration_number,
            'pan_number' => $this->pan_number,
            'vat_number' => $this->vat_number,
            'address' => $this->address,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'authorized_contact_person' => $this->authorized_contact_person,
            'land_rate_government_weight_pct' => $this->land_rate_government_weight_pct,
            'land_rate_market_weight_pct' => $this->land_rate_market_weight_pct,
            'distress_value_pct' => $this->distress_value_pct,
            'is_active' => $this->is_active,
            'branches' => $this->whenLoaded('branches', fn () => $this->branches->map(fn ($b) => [
                'id' => $b->id, 'name_en' => $b->name_en, 'branch_code' => $b->branch_code,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
