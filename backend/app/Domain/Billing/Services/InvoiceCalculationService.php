<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Invoice;
use InvalidArgumentException;

/**
 * Pure arithmetic, deliberately separated from persistence (same pattern
 * as the valuation engines in Phase 5) so the money math is unit-testable
 * without a database and is computed in exactly one place.
 *
 * Nepal tax convention: VAT is added on top and IS part of what the client
 * owes. TDS is withheld BY the client at payment time -- the firm never
 * receives that cash directly, but it isn't lost (it's remitted to the tax
 * authority as an advance credit), so it's treated as settled immediately
 * rather than counted toward `outstanding_amount`.
 *
 *   total_amount       = subtotal + vat_amount - discount_amount
 *   outstanding_amount  = total_amount - tds_amount - paid_amount - credited_amount
 */
class InvoiceCalculationService
{
    /**
     * @param  array<int, array{quantity: float, unit_rate: float}>  $items
     */
    public function computeTotals(array $items, float $vatPct, float $tdsPct, float $discountAmount = 0.0): array
    {
        if ($vatPct < 0 || $tdsPct < 0 || $discountAmount < 0) {
            throw new InvalidArgumentException('VAT %, TDS %, and discount must be non-negative.');
        }

        $lineAmounts = array_map(fn ($item) => round($item['quantity'] * $item['unit_rate'], 2), $items);
        $subtotal = round(array_sum($lineAmounts), 2);

        $vatAmount = round($subtotal * $vatPct / 100, 2);
        $tdsAmount = round($subtotal * $tdsPct / 100, 2);
        $totalAmount = round($subtotal + $vatAmount - $discountAmount, 2);

        if ($totalAmount < 0) {
            throw new InvalidArgumentException('Discount cannot exceed subtotal plus VAT.');
        }

        return [
            'line_amounts' => $lineAmounts,
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'tds_amount' => $tdsAmount,
            'total_amount' => $totalAmount,
        ];
    }

    public function recalculateOutstanding(Invoice $invoice): float
    {
        $outstanding = round(
            (float) $invoice->total_amount
            - (float) $invoice->tds_amount
            - (float) $invoice->paid_amount
            - (float) $invoice->credited_amount,
            2
        );

        return max(0.0, $outstanding);
    }

    public function resolveStatus(Invoice $invoice, float $outstanding): string
    {
        if ($invoice->status === 'cancelled') {
            return 'cancelled';
        }

        if ($outstanding <= 0.0) {
            return 'paid';
        }

        if ((float) $invoice->paid_amount > 0 || (float) $invoice->credited_amount > 0) {
            return $this->isOverdue($invoice) ? 'overdue' : 'partially_paid';
        }

        return $this->isOverdue($invoice) ? 'overdue' : 'issued';
    }

    private function isOverdue(Invoice $invoice): bool
    {
        return $invoice->due_date !== null && $invoice->due_date->isPast();
    }
}
