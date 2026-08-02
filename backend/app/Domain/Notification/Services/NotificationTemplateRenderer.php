<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Models\NotificationTemplate;

/**
 * Resolves a (tenant, event, channel, locale) combination to rendered
 * subject/body text. Tenant-configured templates in `notification_templates`
 * take priority; a small built-in English default covers any combination
 * nobody has configured yet, so the system never fails to notify someone
 * just because a template row is missing -- it degrades to English, not to
 * silence.
 */
class NotificationTemplateRenderer
{
    private const BUILT_IN_DEFAULTS = [
        'report_issued' => [
            'en' => [
                'subject' => 'Valuation report issued — {{assignment_number}}',
                'body' => 'The valuation report for assignment {{assignment_number}} has been issued. You can access it, along with its QR verification link, from your NP-VAMS dashboard.',
            ],
            'ne' => [
                'subject' => 'मूल्याङ्कन प्रतिवेदन जारी — {{assignment_number}}',
                'body' => 'असाइनमेन्ट {{assignment_number}} को मूल्याङ्कन प्रतिवेदन जारी गरिएको छ। तपाईं यसलाई QR प्रमाणीकरण लिंकसहित आफ्नो NP-VAMS ड्यासबोर्डबाट हेर्न सक्नुहुन्छ।',
            ],
        ],
        'correction_requested' => [
            'en' => [
                'subject' => 'Correction requested — {{assignment_number}}',
                'body' => 'A correction has been requested on assignment {{assignment_number}}. Reviewer remarks: {{remarks}}',
            ],
            'ne' => [
                'subject' => 'सुधार अनुरोध गरियो — {{assignment_number}}',
                'body' => 'असाइनमेन्ट {{assignment_number}} मा सुधार अनुरोध गरिएको छ। समीक्षकको टिप्पणी: {{remarks}}',
            ],
        ],
        'valuer_assigned' => [
            'en' => [
                'subject' => 'New assignment for you — {{assignment_number}}',
                'body' => 'You have been assigned as valuer on assignment {{assignment_number}}. Please review the case details and schedule a site visit.',
            ],
            'ne' => [
                'subject' => 'तपाईंको लागि नयाँ असाइनमेन्ट — {{assignment_number}}',
                'body' => 'तपाईंलाई असाइनमेन्ट {{assignment_number}} मा मूल्याङ्कक तोकिएको छ। कृपया विवरण समीक्षा गरी साइट भ्रमणको तालिका बनाउनुहोस्।',
            ],
        ],
        'awaiting_approval' => [
            'en' => [
                'subject' => 'Awaiting your approval — {{assignment_number}}',
                'body' => 'Assignment {{assignment_number}} has passed technical review and is awaiting your final approval.',
            ],
            'ne' => [
                'subject' => 'तपाईंको स्वीकृतिको पर्खाइमा — {{assignment_number}}',
                'body' => 'असाइनमेन्ट {{assignment_number}} प्राविधिक समीक्षा पास गरेर तपाईंको अन्तिम स्वीकृतिको पर्खाइमा छ।',
            ],
        ],
        'approved' => [
            'en' => [
                'subject' => 'Your report has been approved — {{assignment_number}}',
                'body' => 'Assignment {{assignment_number}} has been approved. It will proceed to digital signature and issuance.',
            ],
            'ne' => [
                'subject' => 'तपाईंको प्रतिवेदन स्वीकृत भयो — {{assignment_number}}',
                'body' => 'असाइनमेन्ट {{assignment_number}} स्वीकृत भएको छ। यो डिजिटल हस्ताक्षर र जारी गर्ने चरणमा जानेछ।',
            ],
        ],
        'revaluation_due' => [
            'en' => [
                'subject' => 'Revaluation due — {{assignment_number}}',
                'body' => 'The revaluation period for assignment {{assignment_number}} has been reached. Please initiate a new revaluation assignment if required.',
            ],
            'ne' => [
                'subject' => 'पुनर्मूल्याङ्कन आवश्यक — {{assignment_number}}',
                'body' => 'असाइनमेन्ट {{assignment_number}} को पुनर्मूल्याङ्कन अवधि पुगेको छ। आवश्यक भएमा कृपया नयाँ पुनर्मूल्याङ्कन असाइनमेन्ट सुरु गर्नुहोस्।',
            ],
        ],
        'site_visit_scheduled' => [
            'en' => [
                'subject' => 'Site visit scheduled — {{assignment_number}}',
                'body' => 'A site visit has been scheduled for assignment {{assignment_number}}. Please review the scheduled date and prepare accordingly.',
            ],
            'ne' => [
                'subject' => 'साइट भ्रमण तालिका बनाइयो — {{assignment_number}}',
                'body' => 'असाइनमेन्ट {{assignment_number}} को लागि साइट भ्रमण तालिका बनाइएको छ। कृपया तोकिएको मिति समीक्षा गरी तयारी गर्नुहोस्।',
            ],
        ],
        'inspection_completed' => [
            'en' => [
                'subject' => 'Field inspection completed — {{assignment_number}}',
                'body' => 'Field inspection for assignment {{assignment_number}} has been completed. Technical review can now proceed.',
            ],
            'ne' => [
                'subject' => 'फिल्ड निरीक्षण सम्पन्न — {{assignment_number}}',
                'body' => 'असाइनमेन्ट {{assignment_number}} को फिल्ड निरीक्षण सम्पन्न भएको छ। अब प्राविधिक समीक्षा अगाडि बढाउन सकिन्छ।',
            ],
        ],
        'cancelled' => [
            'en' => [
                'subject' => 'Assignment cancelled — {{assignment_number}}',
                'body' => 'Assignment {{assignment_number}} has been cancelled. Remarks: {{remarks}}',
            ],
            'ne' => [
                'subject' => 'असाइनमेन्ट रद्द गरियो — {{assignment_number}}',
                'body' => 'असाइनमेन्ट {{assignment_number}} रद्द गरिएको छ। टिप्पणी: {{remarks}}',
            ],
        ],
        'superseded' => [
            'en' => [
                'subject' => 'Report superseded — {{assignment_number}}',
                'body' => 'The report for assignment {{assignment_number}} has been superseded. Remarks: {{remarks}}',
            ],
            'ne' => [
                'subject' => 'प्रतिवेदन प्रतिस्थापित गरियो — {{assignment_number}}',
                'body' => 'असाइनमेन्ट {{assignment_number}} को प्रतिवेदन प्रतिस्थापित गरिएको छ। टिप्पणी: {{remarks}}',
            ],
        ],
        'invoice_issued' => [
            'en' => [
                'subject' => 'Invoice issued — {{invoice_number}}',
                'body' => 'Invoice {{invoice_number}} for NRs. {{total_amount}} has been issued. It is due by {{due_date}}.',
            ],
            'ne' => [
                'subject' => 'बिजक जारी — {{invoice_number}}',
                'body' => 'बिजक {{invoice_number}} रु. {{total_amount}} जारी गरिएको छ। यो {{due_date}} सम्ममा तिर्नुपर्नेछ।',
            ],
        ],
        'payment_received' => [
            'en' => [
                'subject' => 'Payment received — {{invoice_number}}',
                'body' => 'A payment of NRs. {{amount}} has been recorded against invoice {{invoice_number}}. Outstanding balance: NRs. {{outstanding_amount}}.',
            ],
            'ne' => [
                'subject' => 'भुक्तानी प्राप्त — {{invoice_number}}',
                'body' => 'बिजक {{invoice_number}} विरुद्ध रु. {{amount}} भुक्तानी दर्ता गरिएको छ। बाँकी रकम: रु. {{outstanding_amount}}।',
            ],
        ],
    ];

    /**
     * @param  array<string, string>  $tokens
     * @return array{subject: ?string, body: string}
     */
    public function render(string $tenantId, string $eventCode, string $channel, string $locale, array $tokens): array
    {
        $template = NotificationTemplate::where('tenant_id', $tenantId)
            ->where('event_code', $eventCode)
            ->where('channel', $channel)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->first();

        [$subjectTemplate, $bodyTemplate] = $template !== null
            ? [$template->subject, $template->body_template]
            : $this->builtInDefault($eventCode, $locale);

        return [
            'subject' => $subjectTemplate !== null ? $this->substitute($subjectTemplate, $tokens) : null,
            'body' => $this->substitute($bodyTemplate, $tokens),
        ];
    }

    private function builtInDefault(string $eventCode, string $locale): array
    {
        $entry = self::BUILT_IN_DEFAULTS[$eventCode][$locale]
            ?? self::BUILT_IN_DEFAULTS[$eventCode]['en']
            ?? ['subject' => $eventCode, 'body' => $eventCode];

        return [$entry['subject'], $entry['body']];
    }

    private function substitute(string $template, array $tokens): string
    {
        $replacements = [];

        foreach ($tokens as $key => $value) {
            $replacements['{{'.$key.'}}'] = (string) ($value ?? '');
        }

        return strtr($template, $replacements);
    }
}
