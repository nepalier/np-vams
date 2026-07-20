<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('documentable_type'); // polymorphic: ValuationAssignment, Property, PropertyOwner, ...
            $table->uuid('documentable_id');
            $table->string('category'); // land|building|identity_organizational
            $table->string('document_type'); // lalpurja|cadastral_map|building_permit|citizenship|... (master-data driven in a later pass)
            $table->string('document_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('issuing_authority')->nullable();
            $table->boolean('original_seen')->default(false);
            $table->boolean('copy_received')->default(false);
            $table->boolean('online_verified')->default(false);
            $table->boolean('authority_verified')->default(false);
            $table->string('verification_status')->default('received');
            $table->text('verification_remarks')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            // Private-disk path only -- NEVER a public path (Step 1 Section 49).
            $table->string('storage_disk')->default('s3');
            $table->string('file_path');
            $table->string('file_hash_sha256', 64)->nullable();
            $table->unsignedInteger('current_version')->default(1);
            $table->string('confidentiality_level')->default('standard'); // standard|restricted|confidential
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['documentable_type', 'documentable_id']);
            $table->index(['tenant_id', 'file_hash_sha256']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('property_document_id');
            $table->unsignedInteger('version_number');
            $table->string('storage_disk')->default('s3');
            $table->string('file_path');
            $table->string('file_hash_sha256', 64)->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->text('change_remarks')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('property_document_id')->references('id')->on('property_documents')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['property_document_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('property_documents');
    }
};
