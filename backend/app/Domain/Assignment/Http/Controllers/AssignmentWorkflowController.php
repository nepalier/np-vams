<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Http\Controllers;

use App\Domain\Assignment\Http\Requests\TransitionAssignmentRequest;
use App\Domain\Assignment\Http\Resources\AssignmentResource;
use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Workflow\Services\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AssignmentWorkflowController
{
    public function __construct(private readonly WorkflowEngine $workflowEngine) {}

    public function transition(TransitionAssignmentRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $request->user()->can('transition', $assignment) || abort(403);

        try {
            $this->workflowEngine->transition(
                subject: $assignment,
                toStatusCode: $request->string('to_status')->toString(),
                user: $request->user(),
                remarks: $request->input('remarks'),
                request: $request,
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'errors' => [['status' => '422', 'title' => 'InvalidTransition', 'detail' => $e->getMessage()]],
            ], 422);
        }

        return (new AssignmentResource($assignment->fresh()))->response();
    }
}
