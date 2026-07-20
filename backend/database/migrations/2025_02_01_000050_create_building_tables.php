<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Core building shell + floor-wise measurement only in this phase.
     * building_blocks (multi-block sites), building_components (detailed
     * construction-material breakdown), and building_condition_assessments
     * (inspection checklist scoring) are schema'd in the field-inspection /
     * cost-approach phase where they are actually consumed, rather than
     * created here empty and unused.
     */
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('property_id');
            $table->string('building_name')->nullable();
            $table->string('building_type')->nullable();
            $table->string('block_name')->nullable();
            $table->unsignedSmallInteger('number_of_floors')->default(1);
            $table->unsignedSmallInteger('basement_floors')->default(0);
            $table->unsignedSmallInteger('construction_year_bs')->nullable();
            $table->unsignedSmallInteger('completion_year_bs')->nullable();
            $table->unsignedSmallInteger('building_age_years')->nullable();
            $table->string('current_use')->nullable();
            $table->string('approved_use')->nullable();
            $table->string('occupancy')->nullable();
            $table->string('building_permit_number')->nullable();
            $table->date('drawing_approval_date')->nullable();
            $table->string('completion_certificate_number')->nullable();
            $table->string('house_tax_number')->nullable();
            $table->boolean('has_earthquake_damage')->default(false);
            $table->string('retrofitting_status')->nullable();
            $table->string('seismic_vulnerability')->nullable();
            $table->unsignedSmallInteger('remaining_economic_life_years')->nullable();
            $table->string('overall_condition')->nullable();
            $table->string('structural_system')->nullable(); // rcc_frame|load_bearing_masonry|steel|prefab|timber|traditional_masonry|adobe|mixed
            $table->string('foundation_type')->nullable();
            $table->string('roof_type')->nullable();
            $table->json('construction_details')->nullable(); // wall/floor/ceiling/door/window/finish/services etc. -- flexible bag for the long, mostly-descriptive attribute list in Section 13
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
        });

        Schema::create('building_floors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('building_id');
            $table->string('floor_name');
            $table->integer('floor_number'); // basements negative, ground = 0
            $table->decimal('approved_area_sqm', 12, 3)->nullable();
            $table->decimal('measured_area_sqm', 12, 3)->nullable();
            $table->decimal('valuation_area_sqm', 12, 3)->nullable();
            $table->decimal('covered_area_sqm', 12, 3)->nullable();
            $table->decimal('balcony_area_sqm', 12, 3)->nullable();
            $table->decimal('staircase_area_sqm', 12, 3)->nullable();
            $table->decimal('common_area_sqm', 12, 3)->nullable();
            $table->decimal('parking_area_sqm', 12, 3)->nullable();
            $table->decimal('commercial_area_sqm', 12, 3)->nullable();
            $table->decimal('residential_area_sqm', 12, 3)->nullable();
            $table->decimal('unauthorized_area_sqm', 12, 3)->nullable();
            $table->string('floor_use')->nullable();
            $table->unsignedSmallInteger('number_of_rooms')->nullable();
            $table->unsignedSmallInteger('kitchen_count')->nullable();
            $table->unsignedSmallInteger('toilet_count')->nullable();
            $table->unsignedSmallInteger('bathroom_count')->nullable();
            $table->unsignedSmallInteger('store_count')->nullable();
            $table->unsignedTinyInteger('completion_percentage')->default(100);
            $table->string('construction_class')->nullable();
            // Rate/depreciation/adjusted-value are populated by the cost
            // approach engine in the valuation phase, not entered directly
            // here -- kept nullable so this table is ready to receive them.
            $table->decimal('unit_construction_rate', 14, 2)->nullable();
            $table->decimal('depreciation_percentage', 5, 2)->nullable();
            $table->decimal('adjusted_value', 16, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('building_id')->references('id')->on('buildings')->cascadeOnDelete();
            $table->unique(['building_id', 'floor_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_floors');
        Schema::dropIfExists('buildings');
    }
};
