<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Invoice;

/**
 * Section 35: "Client statement". A running-balance ledger of every
 * invoice, payment, and credit note for one client, in chronological
 * order -- the same shape an accountant would recognize from a bank
 * statement, computed fresh from the underlying rows each time rather
 * than a separately-maintained ledger table that could drift from them.
 */
class ClientStatementService
{
    public function generate(string $clientId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $invoices = Invoice::where('client_id', $clientId)
            ->when($fromDate, fn ($q) => $q->where('issue_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('issue_date', '<=', $toDate))
            ->with(['payments', 'creditNotes'])
            ->orderBy('issue_date')
            ->get();

        $entries = [];

        foreach ($invoices as $invoice) {
            $entries[] = [
                'date' => $invoice->issue_date->toDateString(),
                'type' => 'invoice',
                'reference' => $invoice->invoice_number,
                'debit' => (float) $invoice->total_amount,
                'credit' => 0.0,
            ];

            foreach ($invoice->payments as $payment) {
                $entries[] = [
                    'date' => $payment->payment_date->toDateString(),
                    'type' => 'payment',
                    'reference' => $payment->reference_number ?? $payment->id,
                    'debit' => 0.0,
                    'credit' => (float) $payment->amount,
                ];
            }

            foreach ($invoice->creditNotes as $creditNote) {
                $entries[] = [
                    'date' => $creditNote->issued_at->toDateString(),
                    'type' => 'credit_note',
                    'reference' => $creditNote->credit_note_number,
                    'debit' => 0.0,
                    'credit' => (float) $creditNote->amount,
                ];
            }
        }

        usort($entries, fn ($a, $b) => $a['date'] <=> $b['date']);

        $runningBalance = 0.0;
        foreach ($entries as &$entry) {
            $runningBalance += $entry['debit'] - $entry['credit'];
            $entry['running_balance'] = round($runningBalance, 2);
        }
        unset($entry);

        return [
            'client_id' => $clientId,
            'entries' => $entries,
            'closing_balance' => round($runningBalance, 2),
            'total_invoiced' => round((float) $invoices->sum('total_amount'), 2),
            'total_outstanding' => round((float) $invoices->sum('outstanding_amount'), 2),
        ];
    }
}
