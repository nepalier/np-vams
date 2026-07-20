<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\CreditNote;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceItem;
use App\Domain\Billing\Models\Payment;
use App\Domain\MasterData\Models\FiscalYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BillingService
{
    public function __construct(
        private readonly InvoiceCalculationService $calculationService,
        private readonly InvoiceNumberGenerator $numberGenerator,
    ) {}

    /**
     * @param  array<int, array{description: string, quantity: float, unit_rate: float}>  $items
     */
    public function createInvoice(
        string $tenantId,
        string $clientId,
        ?string $assignmentId,
        array $items,
        float $vatPct,
        float $tdsPct,
        float $discountAmount,
        ?string $dueDate,
        ?string $createdByUserId,
    ): Invoice {
        if (count($items) === 0) {
            throw new RuntimeException('An invoice requires at least one line item.');
        }

        $fiscalYear = FiscalYear::where('is_current', true)->firstOrFail();
        $totals = $this->calculationService->computeTotals($items, $vatPct, $tdsPct, $discountAmount);

        return DB::transaction(function () use ($tenantId, $clientId, $assignmentId, $items, $totals, $vatPct, $tdsPct, $discountAmount, $dueDate, $createdByUserId, $fiscalYear) {
            $invoice = Invoice::create([
                'tenant_id' => $tenantId,
                'valuation_assignment_id' => $assignmentId,
                'client_id' => $clientId,
                'invoice_number' => $this->numberGenerator->next($tenantId, $fiscalYear),
                'fiscal_year_id' => $fiscalYear->id,
                'issue_date' => now(),
                'due_date' => $dueDate,
                'subtotal' => $totals['subtotal'],
                'vat_pct' => $vatPct,
                'vat_amount' => $totals['vat_amount'],
                'tds_pct' => $tdsPct,
                'tds_amount' => $totals['tds_amount'],
                'discount_amount' => $discountAmount,
                'total_amount' => $totals['total_amount'],
                'paid_amount' => 0,
                'credited_amount' => 0,
                'status' => 'issued',
                'created_by_user_id' => $createdByUserId,
            ]);

            foreach ($items as $index => $item) {
                InvoiceItem::create([
                    'tenant_id' => $tenantId,
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_rate' => $item['unit_rate'],
                    'amount' => $totals['line_amounts'][$index],
                    'sequence' => $index,
                ]);
            }

            $this->refreshOutstandingAndStatus($invoice);

            return $invoice->fresh('items');
        });
    }

    public function recordPayment(Invoice $invoice, float $amount, string $method, ?string $referenceNumber, ?string $receivedByUserId, ?string $remarks): Payment
    {
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be positive.');
        }

        if ($invoice->status === 'cancelled') {
            throw new RuntimeException('Cannot record a payment against a cancelled invoice.');
        }

        $currentOutstanding = $this->calculationService->recalculateOutstanding($invoice);

        if ($amount > $currentOutstanding + 0.01) { // tolerate float rounding noise only
            throw new RuntimeException("Payment of {$amount} exceeds the outstanding balance of {$currentOutstanding}.");
        }

        return DB::transaction(function () use ($invoice, $amount, $method, $referenceNumber, $receivedByUserId, $remarks) {
            $payment = Payment::create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'payment_date' => now(),
                'amount' => $amount,
                'payment_method' => $method,
                'reference_number' => $referenceNumber,
                'received_by_user_id' => $receivedByUserId,
                'remarks' => $remarks,
            ]);

            $invoice->increment('paid_amount', $amount);
            $this->refreshOutstandingAndStatus($invoice->fresh());

            return $payment;
        });
    }

    public function issueCreditNote(Invoice $invoice, float $amount, string $reason, ?string $issuedByUserId): CreditNote
    {
        if ($amount <= 0) {
            throw new RuntimeException('Credit note amount must be positive.');
        }

        $currentOutstanding = $this->calculationService->recalculateOutstanding($invoice);

        if ($amount > $currentOutstanding + 0.01) {
            throw new RuntimeException("Credit note of {$amount} exceeds the outstanding balance of {$currentOutstanding}.");
        }

        return DB::transaction(function () use ($invoice, $amount, $reason, $issuedByUserId) {
            $creditNote = CreditNote::create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'credit_note_number' => 'CN-'.strtoupper(Str::random(8)),
                'amount' => $amount,
                'reason' => $reason,
                'issued_by_user_id' => $issuedByUserId,
                'issued_at' => now(),
            ]);

            $invoice->increment('credited_amount', $amount);
            $this->refreshOutstandingAndStatus($invoice->fresh());

            return $creditNote;
        });
    }

    private function refreshOutstandingAndStatus(Invoice $invoice): void
    {
        $outstanding = $this->calculationService->recalculateOutstanding($invoice);
        $status = $this->calculationService->resolveStatus($invoice, $outstanding);

        $invoice->forceFill(['outstanding_amount' => $outstanding, 'status' => $status])->save();
    }
}
