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
 * Section 36: queued (Section 49), bilingual via NotificationTemplateRenderer
 * -- tenant-configured template if one exists for this event/channel/locale,
 * otherwise the built-in English/Nepali default. Locale is read from the
 * notifiable user's preferred_locale; falls back to 'en'.
 */
class ReportIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ValuationAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rendered = $this->render($notifiable, 'mail');

        return (new MailMessage)
            ->subject($rendered['subject'] ?? "Valuation report issued — {$this->assignment->assignment_number}")
            ->line($rendered['body']);
    }

    public function toArray(object $notifiable): array
    {
        $rendered = $this->render($notifiable, 'database');

        return [
            'type' => 'report_issued',
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
            eventCode: 'report_issued',
            channel: $channel,
            locale: $locale,
            tokens: ['assignment_number' => $this->assignment->assignment_number],
        );
    }
}
