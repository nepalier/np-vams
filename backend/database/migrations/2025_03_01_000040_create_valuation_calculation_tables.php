<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (assignment, property, method). `input_snapshot` and
     * `computed_details` are JSON, not because the numbers are informal,
     * but because they preserve the EXACT inputs and full intermediate
     * breakdown the engine used -- so a reviewer/approver/auditor can
     * reproduce and verify the figure months later even if master-data
     * rates have since changed. `computed_value` is always server-computed
     * by the relevant Engine service; never accepted directly from a
     * client request (Section 49: "Do not trust client-side calculations").
     */
    public function up(): void
    {
        Schema::create('valuation_calculations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('valuation_assignment_id');
            $table->uuid('property_id')->nullable();
            $table->uuid('land_parcel_id')->nullable();
            $table->uuid('building_id')->nullable();
            $table->string('method'); // government_rate|market_comparison|cost_approach|income_approach|residual
            $table->string('status')->default('draft'); // draft|final
            $table->json('input_snapshot');
            $table->decimal('computed_value', 18, 2);
            $table->json('computed_details')->nullable();
            $table->uuid('calculated_by_user_id')->nullable();
            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('valuation_assignment_id')->references('id')->on('valuation_assignments')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
            $table->foreign('land_parcel_id')->references('id')->on('land_parcels')->nullOnDelete();
            $table->foreign('building_id')->references('id')->on('buildings')->nullOnDelete();
            $table->index(['valuation_assignment_id', 'method']);
        });

        Schema::create('valuation_calculation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('valuation_calculation_id');
            $table->string('item_type'); // comparable|cost_component|income_line|residual_line
            $table->uuid('reference_id')->nullable(); // e.g. comparable_properties.id
            $table->string('label');
            $table->decimal('quantity', 16, 4)->nullable();
            $table->decimal('rate', 16, 4)->nullable();
            $table->decimal('adjustment_factor', 8, 4)->nullable();
            $table->decimal('amount', 18, 2);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('valuation_calculation_id')->references('id')->on('valuation_calculations')->cascadeOnDelete();
        });

        // Now that valuation_calculations exists, wire the FK we deferred
        // in the comparable_adjustments migration.
        Schema::table('comparable_adjustments', function (Blueprint $table) {
            $table->foreign('valuation_calculation_id')->references('id')->on('valuation_calculations')->cascadeOnDelete();
        });

        Schema::create('valuation_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('valuation_assignment_id');
            $table->uuid('property_id')->nullable();
            // {"method": "market_comparison", "value": ..., "reliability_rating": ..., "weight": ...}[]
            $table->json('method_inputs');
            $table->decimal('reconciled_market_value', 18, 2);
            $table->decimal('rounded_market_value', 18, 2);
            $table->uuid('government_land_rate_id')->nullable();
            $table->decimal('government_minimum_value', 18, 2)->nullable();
            $table->decimal('distress_value', 18, 2)->nullable();
            $table->decimal('forced_sale_value', 18, 2)->nullable();
            $table->decimal('mortgage_value', 18, 2)->nullable();
            $table->decimal('insurance_value', 18, 2)->nullable();
            $table->decimal('reinstatement_value', 18, 2)->nullable();
            $table->decimal('book_value', 18, 2)->nullable();
            $table->boolean('is_manual_override')->default(false);
            $table->text('override_justification')->nullable();
            $table->uuid('reconciled_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('valuation_assignment_id')->references('id')->on('valuation_assignments')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
            $table->foreign('government_land_rate_id')->references('id')->on('government_land_rates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valuation_reconciliations');
        Schema::table('comparable_adjustments', function (Blueprint $table) {
            $table->dropForeign(['valuation_calculation_id']);
        });
        Schema::dropIfExists('valuation_calculation_items');
        Schema::dropIfExists('valuation_calculations');
    }
};
