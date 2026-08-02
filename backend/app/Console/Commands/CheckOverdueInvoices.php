<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Notification\Listeners\InvoiceObserver;
use App\Domain\Notification\Notifications\InvoiceEventNotification;
use Illuminate\Console\Command;

/**
 * Section 36: "Payment overdue." Meant to run daily via the scheduler
 * (see routes/console.php) -- finds every invoice past its due_date with
 * a real outstanding balance and notifies that client's portal users.
 * Deliberately idempotent-by-day: uses whereDate on a
 * last_overdue_notification_sent_at-free design (no such column exists
 * yet), so re-running this command the same day will re-notify -- an
 * acceptable simplicity trade-off for a v1, flagged here rather than
 * silently assumed away. A dedup column is the natural follow-on if
 * daily-repeated reminders turn out to be too noisy in practice.
 */
class CheckOverdueInvoices extends Command
{
    protected $signature = 'npvams:check-overdue-invoices';

    protected $description = 'Notify clients about invoices past their due date with an outstanding balance.';

    public function handle(InvoiceObserver $invoiceObserver): int
    {
        $overdueInvoices = Invoice::withoutTenantScope()
            ->where('outstanding_amount', '>', 0)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['cancelled', 'paid'])
            ->get();

        foreach ($overdueInvoices as $invoice) {
            $invoiceObserver->notifyClientPortalUsers(
                $invoice,
                new InvoiceEventNotification($invoice, 'payment_overdue', [
                    'due_date' => $invoice->due_date->toDateString(),
                    'outstanding_amount' => number_format((float) $invoice->outstanding_amount, 2),
                ]),
            );
        }

        $this->info("Checked overdue invoices: {$overdueInvoices->count()} notified.");

        return self::SUCCESS;
    }
}
