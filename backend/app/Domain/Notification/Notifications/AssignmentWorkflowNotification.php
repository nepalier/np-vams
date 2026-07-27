<?php

declare(strict_types=1);

namespace App\Domain\Notification\Notifications;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Notification\Services\NotificationTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic, event-code-parameterized notification -- rather than a new PHP
 * class per lifecycle event (Section 36 lists ~18 of them), this one class
 * handles any of them, keyed by event_code against
 * NotificationTemplateRenderer / notification_templates the same way
 * ReportIssuedNotification and CorrectionRequestedNotification do. Those
 * two remain as dedicated classes since they existed first and work fine;
 * every event added from here on uses this one instead of growing the
 * class count linearly with the event list.
 */
class AssignmentWorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<string, string>  $extraTokens */
    public function __construct(
        private readonly ValuationAssignment $assignment,
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
            ->subject($rendered['subject'] ?? "{$this->eventCode} — {$this->assignment->assignment_number}")
            ->line($rendered['body']);
    }

    public function toArray(object $notifiable): array
    {
        $rendered = $this->render($notifiable, 'database');

        return [
            'type' => $this->eventCode,
            'valuation_assignment_id' => $this->assignment->id,
            'assignment_number' => $this->assignment->assignment_number,
            'message' => $rendered['body'],
        ];
    }

    private function render(object $notifiable, string $channel): array
    {
        $locale = $notifiable->preferred_locale ?? 'en';

        return app(NotificationTemplateRenderer::class)->render(
            tenantId: $this->assignment->tenant_id,
            eventCode: $this->eventCode,
            channel: $channel,
            locale: $locale,
            tokens: array_merge(
                ['assignment_number' => $this->assignment->assignment_number],
                $this->extraTokens,
            ),
        );
    }
}
