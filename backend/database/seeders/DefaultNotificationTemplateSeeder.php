<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Notification\Services\NotificationTemplateRenderer;
use Illuminate\Database\Seeder;

/**
 * Seeds tenant-editable copies of the built-in defaults (see
 * NotificationTemplateRenderer::BUILT_IN_DEFAULTS) so a new tenant has
 * something to actually edit in the admin UI rather than an empty table
 * that silently falls back to hard-coded English/Nepali forever. Reflection
 * is used deliberately here rather than duplicating the same copy a third
 * time -- one canonical source of the default English/Nepali strings.
 */
class DefaultNotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = app('currentTenantId');

        $defaults = (new \ReflectionClass(NotificationTemplateRenderer::class))
            ->getConstant('BUILT_IN_DEFAULTS');

        foreach ($defaults as $eventCode => $locales) {
            foreach ($locales as $locale => $entry) {
                NotificationTemplate::updateOrCreate(
                    ['tenant_id' => $tenantId, 'event_code' => $eventCode, 'channel' => 'mail', 'locale' => $locale],
                    ['subject' => $entry['subject'], 'body_template' => $entry['body'], 'is_active' => true]
                );
            }
        }
    }
}
