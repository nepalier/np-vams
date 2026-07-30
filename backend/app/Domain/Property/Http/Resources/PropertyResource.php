<?php

declare(strict_types=1);

namespace App\Domain\Property\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_code' => $this->property_code,
            'property_name' => $this->property_name,
            'property_type_id' => $this->property_type_id,
            'address' => $this->address,
            'district_id' => $this->district_id,
            'district_name' => $this->whenLoaded('district', fn () => $this->district?->name_en),
            'local_level_id' => $this->local_level_id,
            'local_level_name' => $this->whenLoaded('localLevel', fn () => $this->localLevel?->name_en),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'parcels' => $this->whenLoaded('parcels', fn () => $this->parcels->map(fn ($p) => [
                'id' => $p->id, 'kitta_number' => $p->kitta_number, 'area_considered_sqm' => $p->area_considered_sqm,
            ])),
            'buildings' => $this->whenLoaded('buildings', fn () => $this->buildings->map(fn ($b) => [
                'id' => $b->id, 'building_name' => $b->building_name, 'number_of_floors' => $b->number_of_floors,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
