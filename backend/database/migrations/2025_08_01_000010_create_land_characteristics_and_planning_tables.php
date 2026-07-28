<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sections 11 & 12 of the source spec -- deferred at Phase 3 with a
     * note that they "primarily feed valuation adjustments and land in
     * Phase 5 alongside the market-comparison engine that consumes them."
     * Phase 5 shipped the engine but not these tables; closing that gap
     * now. Both are 1:1 with land_parcels (one characteristics/planning
     * record per parcel), kept as separate tables rather than more columns
     * bolted onto land_parcels since they're conceptually distinct
     * concerns (physical site condition vs. regulatory/zoning status)
     * usually captured at different points in the workflow (field
     * inspection vs. desk research).
     */
    public function up(): void
    {
        Schema::create('land_parcel_characteristics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('land_parcel_id');

            $table->string('plot_shape')->nullable(); // regular|irregular|triangular|l_shaped|other
            $table->decimal('frontage_m', 8, 2)->nullable();
            $table->decimal('average_depth_m', 8, 2)->nullable();
            $table->unsignedTinyInteger('number_of_road_frontages')->nullable();
            $table->boolean('is_corner_plot')->default(false);
            $table->string('ground_level_relative_to_road')->nullable(); // above|below|level
            $table->string('topography')->nullable(); // flat|gentle_slope|steep_slope|undulating
            $table->decimal('slope_percentage', 5, 2)->nullable();
            $table->string('soil_condition')->nullable();
            $table->string('drainage')->nullable(); // good|moderate|poor
            $table->string('flood_exposure')->nullable(); // none|low|moderate|high
            $table->string('landslide_exposure')->nullable();
            $table->decimal('river_proximity_m', 10, 2)->nullable();
            $table->boolean('high_tension_line_proximity')->default(false);
            $table->string('access_type')->nullable(); // motorable|foot_trail|no_direct_access
            $table->decimal('road_width_m', 6, 2)->nullable();
            $table->string('road_surface')->nullable(); // blacktop|graveled|earthen
            $table->string('road_ownership')->nullable(); // public|private|shared
            $table->boolean('motorable_access')->default(false);
            $table->boolean('has_boundary_wall')->default(false);
            $table->boolean('has_encroachment')->default(false);
            $table->text('encroachment_details')->nullable();
            $table->string('subdivision_potential')->nullable(); // none|limited|good
            $table->string('development_potential')->nullable();
            $table->text('environmental_advantage')->nullable();
            $table->boolean('has_scenic_view')->default(false);
            $table->text('adverse_influence')->nullable();
            // 1-5 configurable rating scales (Section 11: "Allow
            // configurable rating scales") -- stored as plain integers
            // rather than an enum so a tenant can define its own rating
            // rubric elsewhere without a schema change.
            $table->unsignedTinyInteger('marketability_rating')->nullable();
            $table->unsignedTinyInteger('saleability_rating')->nullable();
            $table->unsignedTinyInteger('neighbourhood_quality_rating')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('land_parcel_id')->references('id')->on('land_parcels')->cascadeOnDelete();
            $table->unique('land_parcel_id');
        });

        Schema::create('land_parcel_planning', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('land_parcel_id');

            $table->string('existing_land_use')->nullable();
            $table->string('government_land_use_category')->nullable();
            $table->string('zoning_category')->nullable();
            $table->boolean('is_residential_zone')->default(false);
            $table->boolean('is_commercial_zone')->default(false);
            $table->boolean('is_industrial_zone')->default(false);
            $table->boolean('is_agricultural_zone')->default(false);
            $table->boolean('is_forest_zone')->default(false);
            $table->boolean('is_conservation_zone')->default(false);
            $table->boolean('is_heritage_zone')->default(false);
            $table->boolean('has_airport_restriction')->default(false);
            $table->decimal('road_setback_m', 6, 2)->nullable();
            $table->decimal('river_setback_m', 6, 2)->nullable();
            $table->text('right_of_way')->nullable();
            $table->decimal('max_building_coverage_pct', 5, 2)->nullable();
            $table->decimal('floor_area_ratio', 6, 3)->nullable();
            $table->decimal('max_height_m', 6, 2)->nullable();
            $table->text('municipal_restrictions')->nullable();
            $table->boolean('has_acquisition_notice')->default(false);
            $table->boolean('has_road_expansion_notice')->default(false);
            $table->text('proposed_infrastructure')->nullable();
            $table->string('building_regulation_reference')->nullable();
            $table->string('compliance_status')->default('unknown'); // compliant|non_compliant|pending_review|unknown
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('land_parcel_id')->references('id')->on('land_parcels')->cascadeOnDelete();
            $table->unique('land_parcel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('land_parcel_planning');
        Schema::dropIfExists('land_parcel_characteristics');
    }
};
