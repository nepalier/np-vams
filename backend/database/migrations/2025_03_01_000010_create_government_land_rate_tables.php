<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Government minimum land rates (Step 1 Section 20) are treated as
     * PLATFORM-LEVEL shared reference data (no tenant_id) rather than
     * per-tenant: these are officially published rates identical for every
     * valuation firm operating in a given district/fiscal-year, so
     * duplicating them per tenant would just create N copies of the same
     * public fact and let them drift out of sync with each other. Entry
     * and approval is still controlled by role/permission
     * (`government_rates.manage` / `.approve`), just not scoped to one
     * tenant's data.
     *
     * "Never overwrite historical fiscal-year rates" (Section 20) is
     * enforced structurally: rows are never UPDATEd after approval, only
     * superseded -- a correction creates a new row with an incremented
     * `version` and sets `superseded_by_id` on the old row, so the full
     * version chain and every historical fiscal year stays queryable.
     */
    public function up(): void
    {
        Schema::create('government_land_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years');
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('district_id')->constrained('districts');
            $table->string('land_revenue_office')->nullable();
            $table->foreignId('local_level_id')->nullable()->constrained('local_levels')->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->string('former_vdc')->nullable();
            $table->string('location')->nullable();
            $table->string('road')->nullable();
            $table->string('land_category')->nullable();
            $table->foreignId('rate_unit_id')->constrained('area_units');
            $table->decimal('minimum_rate', 16, 2);
            $table->date('effective_date');
            $table->string('source_document')->nullable();
            $table->string('source_page')->nullable();
            $table->uuid('verified_by_user_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('approval_status')->default('pending'); // pending|approved|rejected
            $table->unsignedInteger('version')->default(1);
            $table->uuid('superseded_by_id')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->foreign('superseded_by_id')->references('id')->on('government_land_rates')->nullOnDelete();
            $table->index(['district_id', 'fiscal_year_id', 'is_current']);
            $table->index(['local_level_id', 'ward_id', 'fiscal_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_land_rates');
    }
};
