<?php

declare(strict_types=1);

namespace App\Domain\Report\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'valuation_assignment_id' => $this->valuation_assignment_id,
            'report_number' => $this->report_number,
            'status' => $this->status,
            'is_locked' => $this->is_locked,
            'current_version' => $this->whenLoaded('currentVersion', fn () => [
                'id' => $this->currentVersion->id,
                'version_number' => $this->currentVersion->version_number,
                'format' => $this->currentVersion->format,
                'file_hash_sha256' => $this->currentVersion->file_hash_sha256,
                'generated_at' => $this->currentVersion->generated_at,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
