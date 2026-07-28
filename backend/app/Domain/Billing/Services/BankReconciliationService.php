<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BankStatementLine;
use App\Domain\Billing\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Section 35: "Bank reconciliation." Import statement lines (from a CSV
 * export any bank provides), then auto-match against recorded Payment
 * rows by exact amount + date proximity. Auto-match is deliberately
 * conservative: it only ever matches when exactly ONE payment candidate
 * fits, never picks "the closest" among several ambiguous candidates --
 * an ambiguous case is left for a human to resolve manually rather than
 * risk silently reconciling the wrong payment.
 */
class BankReconciliationService
{
    public function __construct(private readonly int $dateToleranceDays = 3) {}

    /**
     * @param  array<int, array{transaction_date: string, description?: string, reference_number?: string, amount: float}>  $rows
     */
    public function import(array $rows, string $tenantId, ?string $importedByUserId): array
    {
        $batchId = (string) Str::uuid();

        $lines = DB::transaction(function () use ($rows, $tenantId, $importedByUserId, $batchId) {
            $created = [];

            foreach ($rows as $row) {
                $created[] = BankStatementLine::create([
                    'tenant_id' => $tenantId,
                    'transaction_date' => $row['transaction_date'],
                    'description' => $row['description'] ?? null,
                    'reference_number' => $row['reference_number'] ?? null,
                    'amount' => $row['amount'],
                    'import_batch_id' => $batchId,
                    'imported_by_user_id' => $importedByUserId,
                ]);
            }

            return $created;
        });

        return ['batch_id' => $batchId, 'imported_count' => count($lines)];
    }

    /** @return array{matched: int, ambiguous: int, unmatched: int} */
    public function autoMatch(string $tenantId, string $batchId): array
    {
        $lines = BankStatementLine::where('tenant_id', $tenantId)
            ->where('import_batch_id', $batchId)
            ->where('is_matched', false)
            ->get();

        $matched = 0;
        $ambiguous = 0;
        $unmatched = 0;

        foreach ($lines as $line) {
            $candidates = Payment::where('tenant_id', $tenantId)
                ->where('amount', $line->amount)
                ->whereBetween('payment_date', [
                    $line->transaction_date->copy()->subDays($this->dateToleranceDays),
                    $line->transaction_date->copy()->addDays($this->dateToleranceDays),
                ])
                ->whereDoesntHave('bankStatementLine') // a payment already matched to another line is not a candidate again
                ->get();

            if ($candidates->count() === 1) {
                $this->markMatched($line, $candidates->first(), 'auto');
                $matched++;
            } elseif ($candidates->count() > 1) {
                $ambiguous++;
            } else {
                $unmatched++;
            }
        }

        return compact('matched', 'ambiguous', 'unmatched');
    }

    public function matchManually(BankStatementLine $line, Payment $payment): BankStatementLine
    {
        if ($line->is_matched) {
            throw new RuntimeException('This statement line is already matched.');
        }

        if ((float) $line->amount !== (float) $payment->amount) {
            throw new RuntimeException(
                "Amount mismatch: statement line is {$line->amount}, payment is {$payment->amount}. ".
                'Manual matches still require the amounts to agree exactly.'
            );
        }

        $this->markMatched($line, $payment, 'manual');

        return $line->fresh();
    }

    public function unmatchedSummary(string $tenantId): array
    {
        return [
            'unmatched_statement_lines' => BankStatementLine::where('tenant_id', $tenantId)->where('is_matched', false)->count(),
            'unmatched_total_amount' => (float) BankStatementLine::where('tenant_id', $tenantId)->where('is_matched', false)->sum('amount'),
        ];
    }

    private function markMatched(BankStatementLine $line, Payment $payment, string $method): void
    {
        $line->forceFill([
            'is_matched' => true,
            'matched_payment_id' => $payment->id,
            'match_method' => $method,
        ])->save();
    }
}
