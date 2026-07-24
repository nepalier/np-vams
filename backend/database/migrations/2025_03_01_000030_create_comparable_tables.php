<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparable_properties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignId('property_type_id')->nullable()->constrained('property_types')->nullOnDelete();
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->date('transaction_date')->nullable();
            $table->date('offer_date')->nullable();
            $table->decimal('parcel_area_sqm', 14, 4)->nullable();
            $table->decimal('built_up_area_sqm', 14, 4)->nullable();
            $table->decimal('road_width_m', 8, 2)->nullable();
            $table->string('road_surface')->nullable();
            $table->decimal('frontage_m', 8, 2)->nullable();
            $table->string('shape')->nullable();
            $table->string('land_use')->nullable();
            $table->boolean('is_corner')->default(false);
            $table->decimal('transaction_value', 16, 2)->nullable();
            $table->decimal('asking_value', 16, 2)->nullable();
            $table->decimal('verified_value', 16, 2)->nullable();
            $table->decimal('unit_rate', 16, 2)->nullable(); // per sqm, in canonical unit -- basis for market comparison adjustments
            $table->string('data_source')->nullable();
            $table->string('contact_source')->nullable();
            $table->string('verification_status')->default('unverified');
            $table->string('reliability_grade')->default('E'); // A..E per Section 21
            $table->string('file_hash_sha256', 64)->nullable(); // supporting-doc hash, duplicate-detection basis
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['latitude', 'longitude']);
            $table->index(['tenant_id', 'district_id', 'reliability_grade'], 'comparable_props_tenant_district_grade_idx');
        });

        // Adjustment factors actually APPLIED to a given comparable within
        // one valuation_calculation (see next migration) -- kept separate
        // from comparable_properties itself because the same comparable can
        // be reused across multiple assignments with different adjustment
        // factors each time (distance/time-decay differs per subject).
        Schema::create('comparable_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('valuation_calculation_id');
            $table->uuid('comparable_property_id');
            $table->decimal('distance_from_subject_m', 10, 2)->nullable();
            $table->decimal('weight', 6, 4)->default(1);
            // {"time": 1.02, "location": 0.95, "road_width": 1.03, ...} --
            // each value is a MULTIPLIER (1.00 = no adjustment), matching
            // Section 22's "Adjusted unit rate = base × combined factors".
            $table->json('adjustment_factors');
            $table->decimal('adjusted_unit_rate', 16, 2); // computed, persisted for audit -- never recomputed silently later
            $table->text('justification')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('comparable_property_id')->references('id')->on('comparable_properties')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparable_adjustments');
        Schema::dropIfExists('comparable_properties');
    }
};
