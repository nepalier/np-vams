<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('property_code')->nullable(); // human-readable, tenant-scoped sequence
            $table->string('property_name')->nullable();
            $table->foreignId('property_type_id')->nullable()->constrained('property_types')->nullOnDelete();
            $table->string('property_subtype')->nullable();
            $table->string('property_use')->nullable();
            $table->string('proposed_use')->nullable();
            $table->string('ownership_type')->nullable();
            $table->string('occupancy_status')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->foreignId('local_level_id')->nullable()->constrained('local_levels')->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->string('tole')->nullable();
            $table->string('road_name')->nullable();
            $table->string('landmark')->nullable();
            // MySQL/MariaDB on shared hosting varies too much in spatial-type
            // and spatial-index support to rely on natively (unlike the
            // PostGIS-backed Postgres deployment this schema was originally
            // designed for) -- plain lat/lng decimals are the portable
            // choice. Distance/area calculations move to application-level
            // haversine math instead of DB spatial functions; a future GIS
            // phase can reintroduce spatial columns behind a driver check
            // if the deployment target is confirmed to be Postgres+PostGIS.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('elevation_m', 8, 2)->nullable();
            $table->string('survey_sheet_number')->nullable();
            $table->string('land_revenue_office')->nullable();
            $table->string('survey_office')->nullable();
            $table->string('area_classification')->nullable(); // urban|semi_urban|rural
            $table->decimal('distance_from_major_road_m', 10, 2)->nullable();
            $table->decimal('distance_from_market_m', 10, 2)->nullable();
            $table->decimal('distance_from_school_m', 10, 2)->nullable();
            $table->decimal('distance_from_hospital_m', 10, 2)->nullable();
            $table->decimal('distance_from_public_transport_m', 10, 2)->nullable();
            $table->text('nearby_infrastructure')->nullable();
            $table->text('location_description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'property_code']);
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('land_parcels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('property_id');
            $table->string('kitta_number')->nullable();
            $table->string('survey_sheet_number')->nullable();
            $table->string('lalpurja_number')->nullable();
            $table->string('former_vdc_or_municipality')->nullable();
            $table->foreignId('local_level_id')->nullable()->constrained('local_levels')->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->string('land_revenue_office')->nullable();
            $table->string('survey_office')->nullable();
            $table->string('land_category')->nullable();
            $table->string('land_use_category')->nullable();

            // Areas are stored BOTH in the unit as originally entered
            // (never overwritten -- Step 1 Section 10) AND normalized to
            // square metres for calculation, so downstream valuation math
            // never has to re-derive the conversion or guess which unit a
            // raw number is in.
            $table->decimal('area_lalpurja', 14, 4)->nullable();
            $table->foreignId('area_lalpurja_unit_id')->nullable()->constrained('area_units')->nullOnDelete();
            $table->decimal('area_lalpurja_sqm', 14, 4)->nullable();

            $table->decimal('area_cadastral', 14, 4)->nullable();
            $table->foreignId('area_cadastral_unit_id')->nullable()->constrained('area_units')->nullOnDelete();
            $table->decimal('area_cadastral_sqm', 14, 4)->nullable();

            $table->decimal('area_site_measured', 14, 4)->nullable();
            $table->foreignId('area_site_measured_unit_id')->nullable()->constrained('area_units')->nullOnDelete();
            $table->decimal('area_site_measured_sqm', 14, 4)->nullable();

            $table->decimal('area_considered_sqm', 14, 4)->nullable(); // the area the valuer ultimately adopts for calculation
            $table->decimal('area_affected_road_widening_sqm', 14, 4)->nullable();
            $table->decimal('area_affected_setback_sqm', 14, 4)->nullable();
            $table->decimal('area_affected_river_sqm', 14, 4)->nullable();
            $table->decimal('area_affected_transmission_line_sqm', 14, 4)->nullable();
            $table->decimal('area_encroached_sqm', 14, 4)->nullable();
            $table->decimal('area_net_usable_sqm', 14, 4)->nullable();

            $table->date('acquisition_date')->nullable();
            $table->string('registration_deed_number')->nullable();
            $table->string('mortgage_status')->nullable();
            $table->string('encumbrance_status')->nullable();
            $table->boolean('has_court_dispute')->default(false);
            $table->text('easement')->nullable();
            $table->text('right_of_way')->nullable();
            $table->text('lease_information')->nullable();
            $table->json('four_boundaries')->nullable(); // {north, south, east, west}
            // Array of {lat, lng} vertices in order -- the portable
            // replacement for a native PostGIS polygon column (see the
            // properties table above for why). Area/contains-point
            // calculations move to application code (e.g. a shoelace-
            // formula helper) rather than a DB spatial function.
            $table->json('boundary_points')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->unique(['property_id', 'kitta_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('land_parcels');
        Schema::dropIfExists('properties');
    }
};
