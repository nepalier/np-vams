<?php

declare(strict_types=1);

namespace App\Domain\Notification\Notifications;

use App\Domain\Notification\Services\NotificationTemplateRenderer;
use App\Domain\Professional\Models\ProfessionalProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Same generic shape as AssignmentWorkflowNotification/
 * InvoiceEventNotification, keyed to a ProfessionalProfile -- Section 5:
 * "Generate automated alerts before license or registration expiry."
 */
class RegistrationExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ProfessionalProfile $profile, private readonly string $expiryDate) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rendered = $this->render($notifiable, 'mail');

        return (new MailMessage)->subject($rendered['subject'])->line($rendered['body']);
    }

    public function toArray(object $notifiable): array
    {
        $rendered = $this->render($notifiable, 'database');

        return [
            'type' => 'registration_expiring',
            'professional_profile_id' => $this->profile->id,
            'expiry_date' => $this->expiryDate,
            'message' => $rendered['body'],
        ];
    }

    private function render(object $notifiable, string $channel): array
    {
        $locale = $notifiable->preferred_locale ?? 'en';

        return app(NotificationTemplateRenderer::class)->render(
            tenantId: $this->profile->tenant_id,
            eventCode: 'registration_expiring',
            channel: $channel,
            locale: $locale,
            tokens: ['expiry_date' => $this->expiryDate],
        );
    }
}
