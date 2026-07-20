<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('client_id')->nullable(); // null = tenant-wide default template
            $table->string('name');
            $table->string('language')->default('bilingual'); // en|ne|bilingual
            $table->string('blade_view'); // e.g. "reports.templates.default_bank_collateral"
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });

        // One logical "report" per assignment -- the mutable pointer to
        // "whichever version is current". The report itself is NEVER
        // edited in place once issued (Section 34); every content change
        // after that point is a new report_versions row, and `reports`
        // just repoints `current_version_id` to it.
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('valuation_assignment_id');
            $table->uuid('report_template_id')->nullable();
            $table->string('report_number')->nullable(); // assigned at first issuance, stable across versions
            $table->string('status')->default('drafting'); // drafting|draft_generated|approved|signed|issued|cancelled|superseded
            $table->uuid('current_version_id')->nullable();
            $table->boolean('is_locked')->default(false); // true from the moment it's approved onward
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('valuation_assignment_id')->references('id')->on('valuation_assignments')->cascadeOnDelete();
            $table->foreign('report_template_id')->references('id')->on('report_templates')->nullOnDelete();
            $table->unique('valuation_assignment_id');
            $table->unique(['tenant_id', 'report_number']);
        });

        Schema::create('report_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('report_id');
            $table->unsignedInteger('version_number');
            $table->string('format'); // docx|pdf|signed_pdf
            $table->string('storage_disk')->default('private_documents');
            $table->string('file_path');
            $table->string('file_hash_sha256', 64); // Section 34: SHA-256 hash, tamper-detection basis
            $table->uuid('generated_by_user_id')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->uuid('superseded_by_id')->nullable();
            $table->text('supersede_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('report_id')->references('id')->on('reports')->cascadeOnDelete();
            $table->foreign('superseded_by_id')->references('id')->on('report_versions')->nullOnDelete();
            $table->unique(['report_id', 'version_number']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('report_versions')->nullOnDelete();
        });

        Schema::create('digital_signatures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('report_version_id');
            $table->uuid('signed_by_user_id');
            $table->string('signer_name'); // denormalized snapshot -- must survive the signer's profile changing later
            $table->string('signer_license_number')->nullable();
            $table->string('certificate_serial')->nullable();
            $table->string('certificate_issuer')->nullable();
            $table->timestamp('certificate_valid_from')->nullable();
            $table->timestamp('certificate_valid_until')->nullable();
            $table->string('organization_seal_path')->nullable();
            $table->string('signed_file_hash_sha256', 64); // hash of the file AT THE MOMENT of signing
            $table->timestamp('signed_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('report_version_id')->references('id')->on('report_versions')->cascadeOnDelete();
            $table->unique('report_version_id'); // a given file version is signed at most once
        });

        Schema::create('qr_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('report_id');
            $table->string('public_token', 40)->unique(); // opaque, unguessable -- the ONLY thing exposed in the QR code / URL
            $table->string('status')->default('valid'); // valid|cancelled|superseded|expired
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('report_id')->references('id')->on('reports')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_verifications');
        Schema::dropIfExists('digital_signatures');
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('report_versions');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('report_templates');
    }
};
