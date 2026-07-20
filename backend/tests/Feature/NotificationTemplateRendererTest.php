<?php

use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Notification\Services\NotificationTemplateRenderer;
use App\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);
    $this->renderer = new NotificationTemplateRenderer;
});

test('falls back to the built-in English default when no template is configured', function () {
    $result = $this->renderer->render($this->tenant->id, 'report_issued', 'mail', 'en', [
        'assignment_number' => 'VAL-2082-000001',
    ]);

    expect($result['subject'])->toContain('VAL-2082-000001');
    expect($result['body'])->toContain('VAL-2082-000001');
});

test('falls back to the built-in Nepali default when locale is ne and no template is configured', function () {
    $result = $this->renderer->render($this->tenant->id, 'report_issued', 'mail', 'ne', [
        'assignment_number' => 'VAL-2082-000001',
    ]);

    expect($result['subject'])->toContain('VAL-2082-000001');
    expect($result['subject'])->not->toBe(
        $this->renderer->render($this->tenant->id, 'report_issued', 'mail', 'en', ['assignment_number' => 'VAL-2082-000001'])['subject']
    );
});

test('a tenant-configured template takes priority over the built-in default', function () {
    NotificationTemplate::create([
        'tenant_id' => $this->tenant->id,
        'event_code' => 'report_issued',
        'channel' => 'mail',
        'locale' => 'en',
        'subject' => 'Custom subject for {{assignment_number}}',
        'body_template' => 'Custom body mentioning {{assignment_number}}.',
        'is_active' => true,
    ]);

    $result = $this->renderer->render($this->tenant->id, 'report_issued', 'mail', 'en', [
        'assignment_number' => 'VAL-2082-000042',
    ]);

    expect($result['subject'])->toBe('Custom subject for VAL-2082-000042');
    expect($result['body'])->toBe('Custom body mentioning VAL-2082-000042.');
});

test('an inactive template is ignored in favour of the built-in default', function () {
    NotificationTemplate::create([
        'tenant_id' => $this->tenant->id,
        'event_code' => 'report_issued',
        'channel' => 'mail',
        'locale' => 'en',
        'subject' => 'Should not be used',
        'body_template' => 'Should not be used',
        'is_active' => false,
    ]);

    $result = $this->renderer->render($this->tenant->id, 'report_issued', 'mail', 'en', ['assignment_number' => 'X']);

    expect($result['subject'])->not->toBe('Should not be used');
});
