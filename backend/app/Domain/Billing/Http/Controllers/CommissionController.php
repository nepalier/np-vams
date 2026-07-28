<?php

declare(strict_types=1);

namespace App\Domain\Billing\Http\Controllers;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Billing\Http\Requests\CalculateCommissionRequest;
use App\Domain\Billing\Models\ValuerCommission;
use App\Domain\Billing\Services\CommissionService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CommissionController
{
    public function __construct(private readonly CommissionService $service) {}

    public function store(CalculateCommissionRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $valuer = User::findOrFail($request->input('user_id'));

        try {
            $commission = $this->service->calculate(
                $assignment,
                $valuer,
                $request->string('commission_type')->toString(),
                $request->float('commission_rate_pct') ?: null,
                $request->float('fixed_amount') ?: null,
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return response()->json(['data' => $commission], 201);
    }

    public function approve(Request $request, ValuerCommission $commission): JsonResponse
    {
        $request->user()->can('invoices.record_payment') || abort(403);

        try {
            $commission = $this->service->approve($commission, $request->user());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return response()->json(['data' => $commission]);
    }

    public function markPaid(Request $request, ValuerCommission $commission): JsonResponse
    {
        $request->user()->can('invoices.record_payment') || abort(403);

        try {
            $commission = $this->service->markPaid($commission, $request->input('payment_reference'));
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return response()->json(['data' => $commission]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->user()->can('invoices.view') || abort(403);

        $commissions = ValuerCommission::query()
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->string('user_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($commissions);
    }

    private function error(string $message): JsonResponse
    {
        return response()->json([
            'errors' => [['status' => '422', 'title' => 'CommissionError', 'detail' => $message]],
        ], 422);
    }
}
