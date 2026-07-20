<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scheduling + outcome record for a site visit. The offline-first PWA
     * sync mechanics (local encrypted storage, conflict detection, retry
     * queue) described in Step 1 Section 46 are a frontend/service-worker
     * concern layered on top of this same table via the sync_status /
     * synced_at columns -- schema'd here so that phase can be built without
     * another migration, but the actual PWA is a separate phase.
     */
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('valuation_assignment_id');
            $table->uuid('property_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->boolean('owner_representative_confirmed')->default(false);
            $table->string('owner_representative_name')->nullable();
            $table->json('field_checklist')->nullable();
            $table->text('field_notes')->nullable();
            $table->boolean('inspection_completed')->default(false);
            $table->timestamp('inspection_completed_at')->nullable();
            $table->string('inspection_signature_path')->nullable();
            $table->json('witness_information')->nullable();
            $table->string('status')->default('scheduled'); // scheduled|checked_in|in_progress|completed|cancelled
            $table->string('sync_status')->default('synced'); // synced|pending|conflict
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('valuation_assignment_id')->references('id')->on('valuation_assignments')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
        });

        Schema::create('site_visit_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('site_visit_id');
            $table->uuid('user_id');
            $table->string('role_on_visit')->nullable(); // valuer|surveyor|witness

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('site_visit_id')->references('id')->on('site_visits')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['site_visit_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visit_members');
        Schema::dropIfExists('site_visits');
    }
};
