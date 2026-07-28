<?php

declare(strict_types=1);

namespace App\Domain\Report\Http\Controllers;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Report\Http\Requests\CancelOrSupersedeReportRequest;
use App\Domain\Report\Http\Requests\SignReportRequest;
use App\Domain\Report\Http\Resources\ReportResource;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Services\ReportWorkflowService;
use App\Domain\Valuation\Models\ValuationReconciliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ReportController
{
    public function __construct(private readonly ReportWorkflowService $service) {}

    public function show(ValuationAssignment $assignment): JsonResponse
    {
        request()->user()->can('view', $assignment) || abort(403);

        $report = Report::where('valuation_assignment_id', $assignment->id)->with('currentVersion')->first();

        return response()->json(['data' => $report]);
    }

    public function generateDraft(Request $request, ValuationAssignment $assignment): JsonResponse
    {
        $request->user()->can('view', $assignment) || abort(403);

        $reconciliation = ValuationReconciliation::where('valuation_assignment_id', $assignment->id)
            ->latest('created_at')
            ->first();

        if ($reconciliation === null) {
            return $this->error('A valuation reconciliation must exist before a report can be generated. Reconcile the methods first.');
        }

        try {
            $report = $this->service->generateDraft(
                $assignment,
                $reconciliation,
                $reconciliation->method_inputs,
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return (new ReportResource($report->load('currentVersion')))->response()->setStatusCode(201);
    }

    public function sign(SignReportRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $report = Report::where('valuation_assignment_id', $assignment->id)->firstOrFail();

        try {
            $report = $this->service->sign($report, $assignment, $request->user(), $request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return (new ReportResource($report->load('currentVersion')))->response();
    }

    public function issue(Request $request, ValuationAssignment $assignment): JsonResponse
    {
        $report = Report::where('valuation_assignment_id', $assignment->id)->firstOrFail();

        try {
            $report = $this->service->issue($report, $assignment, $request->user());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return (new ReportResource($report->load('currentVersion')))->response();
    }

    private function error(string $message): JsonResponse
    {
        return response()->json([
            'errors' => [['status' => '422', 'title' => 'ReportWorkflowError', 'detail' => $message]],
        ], 422);
    }

    public function cancel(CancelOrSupersedeReportRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $report = Report::where('valuation_assignment_id', $assignment->id)->firstOrFail();

        try {
            $report = $this->service->cancel($report, $assignment, $request->user(), $request->string('reason')->toString());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return (new ReportResource($report))->response();
    }

    public function supersede(CancelOrSupersedeReportRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $report = Report::where('valuation_assignment_id', $assignment->id)->firstOrFail();

        try {
            $report = $this->service->supersede($report, $assignment, $request->user(), $request->string('reason')->toString());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return (new ReportResource($report))->response();
    }
}
