<?php

declare(strict_types=1);

namespace App\Domain\Billing\Http\Controllers;

use App\Domain\Billing\Http\Requests\ImportBankStatementRequest;
use App\Domain\Billing\Models\BankStatementLine;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\BankReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BankReconciliationController
{
    public function __construct(private readonly BankReconciliationService $service) {}

    /**
     * Deliberately uses PHP's built-in str_getcsv rather than a CSV
     * parsing library -- no new Composer dependency for four columns of
     * plain data, and one fewer package to keep compatible with whatever
     * PHP version a shared host provides.
     */
    public function import(ImportBankStatementRequest $request): JsonResponse
    {
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $expectedHeader = ['transaction_date', 'description', 'reference_number', 'amount'];

        if ($header === false || array_map('strtolower', array_map('trim', $header)) !== $expectedHeader) {
            fclose($handle);

            return response()->json([
                'errors' => [['status' => '422', 'title' => 'InvalidCsvFormat', 'detail' => 'CSV must have header: transaction_date,description,reference_number,amount']],
            ], 422);
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = [
                'transaction_date' => $row[0],
                'description' => $row[1] ?: null,
                'reference_number' => $row[2] ?: null,
                'amount' => (float) $row[3],
            ];
        }
        fclose($handle);

        $result = $this->service->import($rows, $request->user()->tenant_id, $request->user()->id);
        $matchResult = $this->service->autoMatch($request->user()->tenant_id, $result['batch_id']);

        return response()->json(['data' => array_merge($result, ['auto_match' => $matchResult])], 201);
    }

    public function matchManually(Request $request, BankStatementLine $line): JsonResponse
    {
        $request->user()->can('invoices.record_payment') || abort(403);

        $payment = Payment::findOrFail($request->input('payment_id'));

        try {
            $line = $this->service->matchManually($line, $payment);
        } catch (RuntimeException $e) {
            return response()->json([
                'errors' => [['status' => '422', 'title' => 'ReconciliationError', 'detail' => $e->getMessage()]],
            ], 422);
        }

        return response()->json(['data' => $line]);
    }

    public function unmatchedSummary(Request $request): JsonResponse
    {
        $request->user()->can('invoices.view') || abort(403);

        return response()->json(['data' => $this->service->unmatchedSummary($request->user()->tenant_id)]);
    }
}
