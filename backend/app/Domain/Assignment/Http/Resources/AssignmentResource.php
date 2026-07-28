<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Http\Resources;

use App\Domain\Workflow\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_number' => $this->assignment_number,
            'status' => $this->status,
            'available_transitions' => app(WorkflowEngine::class)->availableTransitions($this->resource),
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name_en),
            'assignment_date' => $this->assignment_date?->toDateString(),
            'requested_completion_date' => $this->requested_completion_date?->toDateString(),
            'priority' => $this->priority,
            'valuation_purpose_id' => $this->valuation_purpose_id,
            'valuation_purpose_name' => $this->whenLoaded('valuationPurpose', fn () => $this->valuationPurpose?->name_en),
            'borrower_id' => $this->borrower_id,
            'assigned_valuer_id' => $this->assigned_valuer_id,
            'assigned_valuer_name' => $this->whenLoaded('assignedValuer', fn () => $this->assignedValuer?->name),
            'assigned_surveyor_id' => $this->assigned_surveyor_id,
            'assigned_reviewer_id' => $this->assigned_reviewer_id,
            'assigned_approver_id' => $this->assigned_approver_id,
            'assigned_approver_name' => $this->whenLoaded('assignedApprover', fn () => $this->assignedApprover?->name),
            'total_fee' => $this->total_fee,
            'payment_status' => $this->payment_status,
            'properties' => $this->whenLoaded('properties', fn () => $this->properties->pluck('property_id')),
            'created_at' => $this->created_at,
        ];
    }
}
