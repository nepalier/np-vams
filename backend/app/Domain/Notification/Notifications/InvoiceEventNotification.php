<?php

declare(strict_types=1);

namespace App\Domain\Notification\Notifications;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Notification\Services\NotificationTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Same generic, event-code-parameterized shape as
 * AssignmentWorkflowNotification -- keyed to an Invoice instead of a
 * ValuationAssignment, since invoice_issued/payment_received are
 * genuinely triggered by the Invoice/Payment models, not a workflow
 * transition (that mismatch is exactly why these two events were
 * flagged rather than forced into WorkflowTransitionObserver).
 */
class InvoiceEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<string, string>  $extraTokens */
    public function __construct(
        private readonly Invoice $invoice,
        private readonly string $eventCode,
        private readonly array $extraTokens = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rendered = $this->render($notifiable, 'mail');

        return (new MailMessage)
            ->subject($rendered['subject'] ?? "{$this->eventCode} — {$this->invoice->invoice_number}")
            ->line($rendered['body']);
    }

    public function toArray(object $notifiable): array
    {
        $rendered = $this->render($notifiable, 'database');

        return [
            'type' => $this->eventCode,
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'message' => $rendered['body'],
        ];
    }

    private function render(object $notifiable, string $channel): array
    {
        $locale = $notifiable->preferred_locale ?? 'en';

        return app(NotificationTemplateRenderer::class)->render(
            tenantId: $this->invoice->tenant_id,
            eventCode: $this->eventCode,
            channel: $channel,
            locale: $locale,
            tokens: array_merge(
                ['invoice_number' => $this->invoice->invoice_number],
                $this->extraTokens,
            ),
        );
    }
}
