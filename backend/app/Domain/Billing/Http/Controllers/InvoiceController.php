<?php

declare(strict_types=1);

namespace App\Domain\Billing\Http\Controllers;

use App\Domain\Billing\Http\Requests\CreateInvoiceRequest;
use App\Domain\Billing\Http\Requests\RecordPaymentRequest;
use App\Domain\Billing\Http\Resources\InvoiceResource;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\ClientStatementService;
use App\Domain\Billing\Services\FinancialReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class InvoiceController
{
    public function __construct(private readonly BillingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $request->user()->can('invoices.view') || abort(403);

        $invoices = Invoice::query()
            ->with('client')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->string('client_id')))
            ->orderByDesc('issue_date')
            ->paginate($request->integer('per_page', 20));

        return response()->json($invoices->through(fn ($invoice) => [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_name' => $invoice->client?->name_en,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'total_amount' => $invoice->total_amount,
            'outstanding_amount' => $invoice->outstanding_amount,
            'status' => $invoice->status,
        ]));
    }

    public function show(Invoice $invoice): JsonResponse
    {
        request()->user()->can('invoices.view') || abort(403);

        return response()->json(['data' => $invoice->load(['client', 'items', 'payments', 'creditNotes'])]);
    }

    public function store(CreateInvoiceRequest $request): JsonResponse
    {
        try {
            $invoice = $this->service->createInvoice(
                tenantId: $request->user()->tenant_id,
                clientId: $request->input('client_id'),
                assignmentId: $request->input('valuation_assignment_id'),
                items: $request->input('items'),
                vatPct: (float) $request->input('vat_pct', 0),
                tdsPct: (float) $request->input('tds_pct', 0),
                discountAmount: (float) $request->input('discount_amount', 0),
                dueDate: $request->input('due_date'),
                createdByUserId: $request->user()->id,
            );
        } catch (RuntimeException|\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }

        return (new InvoiceResource($invoice))->response()->setStatusCode(201);
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $this->service->recordPayment(
                $invoice,
                (float) $request->input('amount'),
                $request->string('payment_method')->toString(),
                $request->input('reference_number'),
                $request->user()->id,
                $request->input('remarks'),
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return (new InvoiceResource($invoice->fresh('items')))->response();
    }

    private function error(string $message): JsonResponse
    {
        return response()->json([
            'errors' => [['status' => '422', 'title' => 'BillingError', 'detail' => $message]],
        ], 422);
    }

    public function clientStatement(Request $request, string $clientId, ClientStatementService $service): JsonResponse
    {
        $request->user()->can('invoices.view') || abort(403);

        return response()->json(['data' => $service->generate(
            $clientId,
            $request->input('from_date'),
            $request->input('to_date'),
        )]);
    }

    public function fiscalYearReport(Request $request, int $fiscalYearId, FinancialReportService $service): JsonResponse
    {
        $request->user()->can('invoices.view') || abort(403);

        return response()->json(['data' => $service->fiscalYearSummary($fiscalYearId)]);
    }
}
