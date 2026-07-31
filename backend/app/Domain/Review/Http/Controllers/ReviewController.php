<?php

declare(strict_types=1);

namespace App\Domain\Review\Http\Controllers;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Review\Http\Requests\AddReviewCommentRequest;
use App\Domain\Review\Http\Requests\RecordDecisionRequest;
use App\Domain\Review\Models\ApprovalRecord;
use App\Domain\Review\Models\ReviewComment;
use App\Domain\Review\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class ReviewController
{
    public function __construct(private readonly ReviewService $service) {}

    public function index(ValuationAssignment $assignment): JsonResponse
    {
        request()->user()->can('view', $assignment) || abort(403);

        return response()->json([
            'data' => [
                'comments' => ReviewComment::where('valuation_assignment_id', $assignment->id)->orderByDesc('created_at')->get(),
                'decisions' => ApprovalRecord::where('valuation_assignment_id', $assignment->id)->orderByDesc('decided_at')->get(),
            ],
        ]);
    }

    public function addComment(AddReviewCommentRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $request->user()->can('view', $assignment) || abort(403);

        $comment = $this->service->addComment($assignment, $request->user(), $request->validated());

        return response()->json(['data' => $comment])->setStatusCode(201);
    }

    public function decide(RecordDecisionRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $request->user()->can('view', $assignment) || abort(403);

        try {
            $record = $this->service->recordTechnicalReviewDecision(
                $assignment,
                $request->user(),
                $request->string('decision')->toString(),
                $request->input('remarks'),
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'errors' => [['status' => '422', 'title' => 'ReviewDecisionError', 'detail' => $e->getMessage()]],
            ], 422);
        }

        return response()->json(['data' => $record]);
    }
}
