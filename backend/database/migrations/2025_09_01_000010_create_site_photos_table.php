<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section 18: categorized site photos, auto-watermarked with
     * assignment number/property ID/date/time/lat/lng/valuer name/
     * category, original and watermarked stored SEPARATELY (never
     * overwrite the original), duplicate detection via file hash --
     * matching the same hash-based duplicate-detection pattern already
     * used for property_documents (Phase 3) and comparable_properties
     * (Phase 5), applied here to photos specifically since a distinct
     * site_photos table (Section 39) captures fields a generic document
     * row doesn't: category, GPS coordinates, capture time.
     */
    public function up(): void
    {
        Schema::create('site_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('site_visit_id')->nullable();
            $table->uuid('property_id')->nullable();
            $table->string('category'); // access_road|front_view|rear_view|left_view|right_view|boundary|floor|internal_room|staircase|kitchen|toilet|roof|utility_system|structural_defect|neighbourhood|gps_evidence|document_evidence|other
            $table->string('storage_disk')->default('private_documents');
            $table->string('original_path');
            $table->string('watermarked_path')->nullable();
            $table->string('file_hash_sha256', 64); // hash of the ORIGINAL, unwatermarked bytes -- duplicate detection basis
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->uuid('uploaded_by_user_id')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('site_visit_id')->references('id')->on('site_visits')->nullOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
            $table->foreign('uploaded_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'file_hash_sha256'], 'site_photos_tenant_hash_idx');
            $table->index(['property_id', 'category'], 'site_photos_property_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_photos');
    }
};
