<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section 36: "Provide configurable templates in Nepali and English."
     * Tenant-scoped (a firm may want its own subject-line wording), with a
     * built-in English fallback in code (see NotificationTemplateRenderer)
     * for any (tenant, event, locale) combination that hasn't been
     * configured yet -- so notifications never silently fail to send just
     * because nobody has filled in a template row.
     */
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('event_code'); // e.g. report_issued, correction_requested, assignment_received
            $table->string('channel'); // mail|sms|database|push
            $table->string('locale', 5); // en|ne
            $table->string('subject')->nullable(); // not applicable to sms/push
            $table->text('body_template'); // {{tokens}} substituted at render time
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'event_code', 'channel', 'locale'], 'notification_template_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
