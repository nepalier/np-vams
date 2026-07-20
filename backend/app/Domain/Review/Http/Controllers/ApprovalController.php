<?php

declare(strict_types=1);

namespace App\Domain\Review\Http\Controllers;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Review\Http\Requests\RecordDecisionRequest;
use App\Domain\Review\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class ApprovalController
{
    public function __construct(private readonly ApprovalService $service) {}

    public function decide(RecordDecisionRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $request->user()->can('view', $assignment) || abort(403);

        try {
            $record = $this->service->decide(
                $assignment,
                $request->user(),
                $request->string('decision')->toString(),
                $request->input('remarks'),
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'errors' => [['status' => '422', 'title' => 'ApprovalDecisionError', 'detail' => $e->getMessage()]],
            ], 422);
        }

        return response()->json(['data' => $record]);
    }
}
