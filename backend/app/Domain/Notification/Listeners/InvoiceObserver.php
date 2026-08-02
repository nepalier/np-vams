<?php

declare(strict_types=1);

namespace App\Domain\Notification\Listeners;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Notification\Notifications\InvoiceEventNotification;
use App\Models\User;

/**
 * Fires on Invoice creation (always status='issued' at that point --
 * BillingService::createInvoice never creates a draft state) and notifies
 * every active client-portal user for that invoice's client, if any
 * exist. A client with no portal users configured yet simply gets no
 * notification -- not an error, just nothing to notify.
 */
class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        $this->notifyClientPortalUsers(
            $invoice,
            new InvoiceEventNotification($invoice, 'invoice_issued', [
                'total_amount' => number_format((float) $invoice->total_amount, 2),
                'due_date' => optional($invoice->due_date)->toDateString() ?? 'N/A',
            ]),
        );
    }

    /**
     * Reused by PaymentObserver, which has the amount but needs the
     * SAME client-portal-recipient resolution logic -- kept here rather
     * than duplicated, since "who gets notified about this invoice" is
     * one piece of logic regardless of which event triggered it.
     */
    public function notifyClientPortalUsers(Invoice $invoice, $notification): void
    {
        User::withoutTenantScope()
            ->where('client_id', $invoice->client_id)
            ->where('is_active', true)
            ->get()
            ->each(fn (User $user) => $user->notify($notification));
    }
}
